local WIVBN = WhereIveBeen

function WIVBN.IsSessionActive()
    return WIVBN.sessionId ~= nil
end

function WIVBN.CurrentSession()
    return WIVBN.sessionId and WhereIveBeenDB.sessions[WIVBN.sessionId] or nil
end

function WIVBN.SortedSessionIds()
    local ids = {}
    for id in pairs(WhereIveBeenDB.sessions) do
        ids[#ids + 1] = id
    end
    table.sort(ids)

    return ids
end

function WIVBN.PointCount(session)
    return (session and session.points) and #session.points or 0
end

function WIVBN.TotalPoints()
    local total = 0
    for _, session in pairs(WhereIveBeenDB.sessions) do
        total = total + WIVBN.PointCount(session)
    end

    return total
end

function WIVBN.PurgeEmptySessions()
    local removed = 0

    for id, session in pairs(WhereIveBeenDB.sessions) do
        if WIVBN.PointCount(session) == 0 and id ~= WhereIveBeenDB.activeSessionId then
            WhereIveBeenDB.sessions[id] = nil
            removed = removed + 1
        end
    end

    if removed > 0 then
        print(WIVBN.PREFIX .. ("Removed %d empty sessions"):format(removed))
    end

    return removed
end

function WIVBN.OverBudget(total, count)
    if WIVBN.MAX_POINTS_TOTAL > 0 and total > WIVBN.MAX_POINTS_TOTAL then return true end
    if WIVBN.MAX_SESSIONS > 0 and count > WIVBN.MAX_SESSIONS then return true end

    return false
end

function WIVBN.EnforceBudget()
    if WIVBN.MAX_POINTS_TOTAL <= 0 and WIVBN.MAX_SESSIONS <= 0 then return end

    local db      = WhereIveBeenDB
    local ids     = WIVBN.SortedSessionIds()
    local total   = WIVBN.TotalPoints()
    local count   = #ids
    local freed   = 0

    for _, id in ipairs(ids) do
        if not WIVBN.OverBudget(total, count) then break end

        local session = db.sessions[id]
        if session and session.exportedAt and id ~= db.activeSessionId then
            total = total - WIVBN.PointCount(session)
            count = count - 1
            db.sessions[id] = nil
            freed = freed + 1
        end
    end

    if freed > 0 then
        print(WIVBN.PREFIX .. ("Removed %d exported sessions to free storage"):format(freed))
    end

    if WIVBN.MAX_POINTS_TOTAL > 0 and total > WIVBN.MAX_POINTS_TOTAL then
        print(WIVBN.PREFIX .. ("|cffff8800Point budget: %d of %d used. Export and delete old sessions|r")
            :format(total, WIVBN.MAX_POINTS_TOTAL))
    end

    if WIVBN.MAX_SESSIONS > 0 and count > WIVBN.MAX_SESSIONS then
        print(WIVBN.PREFIX .. ("|cffff8800Session count: %d of %d, only %d points stored.|r")
            :format(count, WIVBN.MAX_SESSIONS, total))
        print(WIVBN.PREFIX .. "Recording continues. Use |cffffd100/wivebeen prune 10|r to drop tiny leftover sessions")
    end
end

function WIVBN.PruneSessions(minPoints)
    local removed, kept = 0, 0

    for id, session in pairs(WhereIveBeenDB.sessions) do
        if id ~= WhereIveBeenDB.activeSessionId and WIVBN.PointCount(session) < minPoints then
            WhereIveBeenDB.sessions[id] = nil
            removed = removed + 1
        else
            kept = kept + 1
        end
    end

    return removed, kept
end

function WIVBN.ShowSessionStatus()
    local session = WIVBN.CurrentSession()

    if session then
        print(WIVBN.PREFIX .. ("Session is active: %d points"):format(#session.points))
    else
        print(WIVBN.PREFIX .. "Session is unactive")
    end

    print(WIVBN.PREFIX .. ("Stored: %d sessions, %d points")
        :format(#WIVBN.SortedSessionIds(), WIVBN.TotalPoints()))
end

local function NextSessionId()
    local base = math.max(time() * 1000, (WhereIveBeenDB.lastSessionId or 0) + 1)

    for offset = 0, 999 do
        local id = base + offset
        if not WhereIveBeenDB.sessions[id] then
            WhereIveBeenDB.lastSessionId = id
            return id
        end
    end

    WhereIveBeenDB.lastSessionId = base + 999

    return base + 999
end

function WIVBN.StartSession(continuesFrom)
    if WIVBN.IsSessionActive() then
        print(WIVBN.PREFIX .. "Current session already started. End or clear current session before")
        return nil
    end

    WIVBN.PurgeEmptySessions()
    WIVBN.EnforceBudget()

    local id = NextSessionId()

    WhereIveBeenDB.sessions[id] = {
        id            = id,
        schema        = WIVBN.SCHEMA,
        started       = time(),
        clock         = GetTime(),
        tBase         = 0,
        char          = UnitName("player"),
        realm         = GetRealmName(),
        faction       = UnitFactionGroup("player"),
        class         = select(2, UnitClass("player")),
        level         = UnitLevel("player"),
        continuesFrom = continuesFrom,
        points        = {},
    }

    WhereIveBeenDB.activeSessionId = id
    WIVBN.sessionId = id
    WIVBN.lastMapId, WIVBN.lastX, WIVBN.lastY = nil, nil, nil

    print(WIVBN.PREFIX .. "Session successfully started")

    return id
end

function WIVBN.ResumeSession(id)
    local session = WhereIveBeenDB.sessions[id]
    if not session then return nil end

    local last    = session.points and session.points[#session.points]
    local lastT   = last and last.t or session.tBase or 0
    local offline = math.max(0, (time() - session.started) - lastT)

    session.tBase = time() - session.started
    session.clock = GetTime()
    session.ended = nil

    WIVBN.sessionId = id
    WIVBN.lastMapId, WIVBN.lastX, WIVBN.lastY = nil, nil, nil

    print(WIVBN.PREFIX .. ("Session resumed: %d points"):format(#session.points))

    if offline >= WIVBN.RESUME_GAP_MIN then
        WIVBN.RefinePoint(WIVBN.SaveEvent({
            event   = "gap",
            reason  = "login",
            seconds = math.floor(offline),
        }))
    end

    return id
end

function WIVBN.ResumeOrIdle()
    local db = WhereIveBeenDB

    WIVBN.PurgeEmptySessions()

    if db.activeSessionId and db.sessions[db.activeSessionId] then
        WIVBN.ResumeSession(db.activeSessionId)
        WIVBN.EnforceBudget()
        return
    end

    db.activeSessionId = nil

    if not db.initialized then
        db.initialized = true
        WIVBN.StartSession()
        return
    end

    print(WIVBN.PREFIX .. "No active session. Type /wivbn start to begin")
end

function WIVBN.EndSession()
    local session = WIVBN.CurrentSession()
    if not session then
        print(WIVBN.PREFIX .. "Session wasn`t created")
        return nil
    end

    WIVBN.FlushPending()

    session.ended = time()

    WIVBN.sessionId = nil
    WhereIveBeenDB.activeSessionId = nil

    print(WIVBN.PREFIX .. "Session successfully ended")
end

function WIVBN.RotateSession()
    if WIVBN.rotating then return nil end

    local previous = WIVBN.sessionId
    if not previous then return nil end

    WIVBN.rotating = true

    WIVBN.EndSession()
    local id = WIVBN.StartSession(previous)

    WIVBN.rotating = false

    if id then
        print(WIVBN.PREFIX .. "Point limit reached, tracking continues in a new session")
    end

    return id
end

function WIVBN.ClearSession()
    local session = WIVBN.CurrentSession()
    if not session then
        print(WIVBN.PREFIX .. "Session wasn`t created")
        return nil
    end

    WIVBN.FlushPending()

    session.points  = {}
    session.started = time()
    session.clock   = GetTime()
    session.tBase   = 0
    session.ended   = nil

    WIVBN.lastMapId, WIVBN.lastX, WIVBN.lastY = nil, nil, nil

    print(WIVBN.PREFIX .. "Session successfully clean")
end

function WIVBN.DeleteSession(id)
    if not WhereIveBeenDB.sessions[id] then return false end

    if WIVBN.sessionId == id then
        WIVBN.FlushPending()
        WIVBN.sessionId = nil
        WhereIveBeenDB.activeSessionId = nil
    end

    WhereIveBeenDB.sessions[id] = nil

    return true
end

function WIVBN.ClearAllSessions()
    WIVBN.FlushPending()

    WIVBN.sessionId = nil
    WhereIveBeenDB.sessions = {}
    WhereIveBeenDB.activeSessionId = nil

    print(WIVBN.PREFIX .. "Sessions successfully clean")
end

function WIVBN.GetCurrentOrLastSession()
    local session = WIVBN.CurrentSession()
    if session then return session end

    local ids = WIVBN.SortedSessionIds()
    local lastId = ids[#ids]

    return lastId and WhereIveBeenDB.sessions[lastId] or nil
end

local MAX_LISTED = 3

local function DescribeItems(p)
    if type(p.items) ~= "table" or #p.items == 0 then
        if not p.itemId and not p.itemName then return nil end

        return ("%s x%s"):format(p.itemName or tostring(p.itemId), tostring(p.count or 1))
    end

    local parts = {}
    for i = 1, math.min(#p.items, MAX_LISTED) do
        local item = p.items[i]
        parts[#parts + 1] = ("%s x%s"):format(item.name or tostring(item.id), tostring(item.n or 1))
    end

    if #p.items > MAX_LISTED then
        parts[#parts + 1] = ("+%d more"):format(#p.items - MAX_LISTED)
    end

    return table.concat(parts, ", ")
end

local function DescribeGroup(p)
    local parts = {}

    if p.joined and #p.joined > 0 then parts[#parts + 1] = "+" .. table.concat(p.joined, ", ") end
    if p.left and #p.left > 0 then parts[#parts + 1] = "-" .. table.concat(p.left, ", ") end

    if #parts == 0 and p.member then
        parts[#parts + 1] = ("%s %s"):format(tostring(p.action), tostring(p.member))
    end

    return table.concat(parts, " ")
end

local function DescribeDeath(p)
    if p.environment then
        return "|cffff0000death|r (" .. tostring(p.environment):lower() .. ")"
    end

    if p.killer and p.killer.name then
        return "|cffff0000death|r by " .. tostring(p.killer.name)
    end

    return "|cffff0000death|r"
end

local function DescribeGather(p)
    local node = p.node and (p.node.name or (p.node.objectId and ("object " .. p.node.objectId))) or "node"
    local items = DescribeItems(p)

    return "|cff34d399gather:|r " .. node .. (items and (" — " .. items) or "")
end

function WIVBN.DescribePoint(p)
    local e = p.event

    if e == "mount"     then return p.mounted and "mounted" or "dismounted" end
    if e == "combat"    then return p.inCombat and "|cffff5555combat started|r" or "combat ended" end
    if e == "death"     then return DescribeDeath(p) end
    if e == "resurrect" then return "|cff00ff00resurrect|r" end
    if e == "levelup"   then return "level "..tostring(p.level) end
    if e == "loot"      then return "loot: "..(DescribeItems(p) or "?") end
    if e == "gather"    then return DescribeGather(p) end
    if e == "zone"      then return "|cffa78bfazone:|r "..tostring(p.zone)..(p.subZone and (" — "..p.subZone) or "") end
    if e == "visit"     then return "visit: "..(p.places and table.concat(p.places, ", ") or tostring(p.place)) end
    if e == "group"     then return "group: "..DescribeGroup(p) end
    if e == "quest"     then return ("quest: %s %s"):format(tostring(p.action), p.title or tostring(p.questId)) end
    if e == "taxi"      then return p.onTaxi and "takeoff (taxi)" or "landing (taxi)" end
    if e == "gap"       then return "|cffff8800route gap|r"..(p.reason and (" ("..p.reason..")") or "") end

    return "path"
end

function WIVBN.FormatPoint(p)
    local info = p.mapId and C_Map.GetMapInfo(p.mapId)
    local zone = (info and info.name) or ("map "..tostring(p.mapId))
    local t    = p.t and ("%.1f"):format(p.t) or "--"

    return ("|cffaaaaaa[%ss]|r %.1f, %.1f  %s  — %s"):format(
        t, (p.x or 0) * 100, (p.y or 0) * 100, zone, WIVBN.DescribePoint(p))
end

function WIVBN.ShowLog()
    local session = WIVBN.GetCurrentOrLastSession()
    if not session then
        print(WIVBN.PREFIX.."No sessions to show")
        return
    end

    local points = session.points
    local total  = #points
    if total == 0 then
        print(WIVBN.PREFIX.."Session has no points yet")
        return
    end

    local limit = 20
    local from  = math.max(1, total - limit + 1)

    print(WIVBN.PREFIX..("last %d of %d points:"):format(total - from + 1, total))
    for i = from, total do
        print(WIVBN.FormatPoint(points[i]))
    end
end
