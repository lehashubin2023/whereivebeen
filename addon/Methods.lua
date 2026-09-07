local WIVBN = WhereIveBeen

local function MigrateActiveSession()
    local db = WhereIveBeenDB
    if not db.activeSessionId then return end

    local session = db.sessions[db.activeSessionId]

    if session and session.char then
        db.activeSessions[session.char .. "-" .. (session.realm or "?")] = db.activeSessionId
    end

    db.activeSessionId = nil
end

function WIVBN.InitDB()
    WhereIveBeenDB = WhereIveBeenDB or {}
    WhereIveBeenDB.sessions = WhereIveBeenDB.sessions or {}
    WhereIveBeenDB.activeSessions = WhereIveBeenDB.activeSessions or {}

    MigrateActiveSession()

    if WhereIveBeenDB.schema == WIVBN.SCHEMA then return end

    for id, session in pairs(WhereIveBeenDB.sessions) do
        session.id     = session.id or id
        session.points = session.points or {}
        session.tBase  = session.tBase or 0
        session.schema = session.schema or 1
    end

    WhereIveBeenDB.schema = WIVBN.SCHEMA
end

function WIVBN.SessionTime(session)
    if not session then return 0 end

    return math.floor(((session.tBase or 0) + (GetTime() - session.clock)) * 10) / 10
end

function WIVBN.GuidId(guid)
    if not guid then return nil end

    local parts = {}
    for part in guid:gmatch("[^%-]+") do
        parts[#parts + 1] = part
    end

    if parts[1] == "Creature" or parts[1] == "Vehicle" or parts[1] == "GameObject" then
        return tonumber(parts[6])
    end

    return nil
end

function WIVBN.NpcInfo()
    if not UnitExists("npc") then return nil end

    return WIVBN.GuidId(UnitGUID("npc")), UnitName("npc")
end

function WIVBN.AccumulateInterval(elapsed)
    WIVBN.timeElapsed = WIVBN.timeElapsed + elapsed
    return WIVBN.timeElapsed
end

function WIVBN.ClearInterval()
    WIVBN.timeElapsed = 0
end

function WIVBN.IsRoutableMap(mapId)
    if not mapId then return false end
    if not C_Map.GetMapInfo then return true end

    local ok, info = pcall(C_Map.GetMapInfo, mapId)
    if not ok or type(info) ~= "table" or not info.mapType then return true end

    return info.mapType >= WIVBN.MIN_MAP_TYPE
end

function WIVBN.GetPlayerPosition()
    local mapId = C_Map.GetBestMapForUnit("player")
    if not WIVBN.IsRoutableMap(mapId) then return nil end

    local position = C_Map.GetPlayerMapPosition(mapId, "player")
    if not position then return nil end

    local x, y = position:GetXY()
    if x == nil or y == nil or (x == 0 and y == 0) then return nil end

    return mapId, x, y
end

function WIVBN.RoundCoord(value)
    if not value then return 0 end

    return math.floor(value * WIVBN.COORD_PRECISION + 0.5) / WIVBN.COORD_PRECISION
end

function WIVBN.WritePoint(mapId, x, y, extra)
    local session = WhereIveBeenDB.sessions[WIVBN.sessionId]

    local point = {
        x     = WIVBN.RoundCoord(x),
        y     = WIVBN.RoundCoord(y),
        mapId = mapId,
        t     = WIVBN.SessionTime(session),
    }
    if extra then
        for k, v in pairs(extra) do point[k] = v end
    end

    table.insert(session.points, point)

    if mapId then
        WIVBN.lastPointByMap[mapId] = { x = point.x, y = point.y }
    end

    local count = #session.points

    if WIVBN.MAX_POINTS_SESSION > 0 and count >= WIVBN.MAX_POINTS_SESSION then
        WIVBN.RotateSession()
    elseif count % WIVBN.WARN_EVERY_POINTS == 0 then
        WIVBN.WarnStorage()
    end

    return point
end

function WIVBN.SavePosition(extra)
    if not WIVBN.IsSessionActive() then return nil end

    local mapId, x, y = WIVBN.GetPlayerPosition()
    if not mapId then return nil end

    return WIVBN.WritePoint(mapId, x, y, extra)
end

function WIVBN.MovedEnough(mapId, x, y)
    local last = WIVBN.lastPointByMap[mapId]
    if not last then return true end

    local dx, dy = x - last.x, y - last.y

    return (dx * dx + dy * dy) >= (WIVBN.MIN_MOVE * WIVBN.MIN_MOVE)
end

function WIVBN.TrackMapEdge()
    if not WIVBN.IsSessionActive() then return end

    local mapId, x, y = WIVBN.GetPlayerPosition()
    if not mapId then return end

    local edge = WIVBN.edge

    if edge and edge.mapId ~= mapId and WIVBN.MovedEnough(edge.mapId, edge.x, edge.y) then
        WIVBN.WritePoint(edge.mapId, edge.x, edge.y)
    end

    WIVBN.edge = { mapId = mapId, x = x, y = y }
end

function WIVBN.SaveEvent(extra)
    if not WIVBN.IsSessionActive() then return nil end

    WIVBN.FlushPending()
    WIVBN.TrackMapEdge()

    local mapId, x, y = WIVBN.GetPlayerPosition()

    local point = WIVBN.WritePoint(mapId, x, y, extra)
    WIVBN.ClearInterval()
    WIVBN.RefinePoint(point)

    return point
end

function WIVBN.RefinePoint(point, attempt)
    if not point or point.mapId then return end

    attempt = attempt or 1
    if attempt > WIVBN.REFINE_ATTEMPTS then return end

    C_Timer.After(WIVBN.REFINE_DELAY, function()
        if point.mapId then return end

        local mapId, x, y = WIVBN.GetPlayerPosition()
        if not mapId then
            WIVBN.RefinePoint(point, attempt + 1)
            return
        end

        point.mapId = mapId
        point.x, point.y = WIVBN.RoundCoord(x), WIVBN.RoundCoord(y)
        WIVBN.lastPointByMap[mapId] = { x = point.x, y = point.y }
    end)
end

function WIVBN.SaveTimedPosition()
    if not WIVBN.IsSessionActive() then return nil end

    local mapId, x, y = WIVBN.GetPlayerPosition()
    if not mapId then return nil end

    if not WIVBN.MovedEnough(mapId, x, y) then return nil end

    WIVBN.SavePosition()
end

function WIVBN.SaveIsMountedState()
    if not WIVBN.IsSessionActive() then return end

    local isMounted = IsMounted() and true or false
    if isMounted ~= WIVBN.wasMounted then
        WIVBN.wasMounted = isMounted
        WIVBN.SaveEvent({ event = "mount", mounted = isMounted })
    end
end

function WIVBN.SaveTaxiState()
    if not WIVBN.IsSessionActive() then return end

    local onTaxi = UnitOnTaxi("player") and true or false
    if onTaxi ~= WIVBN.wasOnTaxi then
        WIVBN.wasOnTaxi = onTaxi
        WIVBN.SaveEvent({ event = "taxi", onTaxi = onTaxi })
    end
end

function WIVBN.SyncStateFlags()
    WIVBN.wasMounted = IsMounted() and true or false
    WIVBN.wasOnTaxi  = UnitOnTaxi("player") and true or false
    WIVBN.wasDead    = UnitIsDeadOrGhost("player") and true or false
    WIVBN.groupRoster = WIVBN.GetGroupRoster()
end

function WIVBN.SaveSessionBaseline()
    if not WIVBN.IsSessionActive() then return end

    WIVBN.SyncStateFlags()
    WIVBN.ResetZoneState()
    WIVBN.edge = nil
    WIVBN.lastPointByMap = {}

    if WIVBN.wasOnTaxi then
        WIVBN.SaveEvent({ event = "taxi", onTaxi = true })
    elseif WIVBN.wasMounted then
        WIVBN.SaveEvent({ event = "mount", mounted = true })
    end

    if UnitAffectingCombat("player") then
        WIVBN.SaveEvent({ event = "combat", inCombat = true })
    end

    if not WIVBN.SaveZoneState() and not WIVBN.SavePosition() then
        WIVBN.SaveEvent({})
    end
end

local function UnitKey(unit)
    local name, realm = UnitName(unit)
    if not name then return nil end

    if realm and realm ~= "" then
        return name .. "-" .. realm
    end

    return name
end

function WIVBN.GetGroupRoster()
    local roster = {}
    if not IsInGroup() then return roster end

    if IsInRaid() then
        for i = 1, GetNumGroupMembers() do
            local unit = "raid" .. i
            if not UnitIsUnit(unit, "player") then
                local key = UnitKey(unit)
                if key then roster[key] = true end
            end
        end
    else
        for i = 1, GetNumSubgroupMembers() do
            local key = UnitKey("party" .. i)
            if key then roster[key] = true end
        end
    end

    return roster
end

function WIVBN.SaveGroupState()
    local current = WIVBN.GetGroupRoster()

    for name in pairs(current) do
        if not WIVBN.groupRoster[name] then
            WIVBN.PushAggregated("group", { action = "join", member = name })
        end
    end
    for name in pairs(WIVBN.groupRoster) do
        if not current[name] then
            WIVBN.PushAggregated("group", { action = "leave", member = name })
        end
    end

    WIVBN.groupRoster = current
end

function WIVBN.OnResurrect()
    if not WIVBN.wasDead then return end
    WIVBN.wasDead = false
    WIVBN.SaveEvent({ event = "resurrect" })
end

function WIVBN.QuestTitle(questId)
    if questId and C_QuestLog and C_QuestLog.GetTitleForQuestID then
        return C_QuestLog.GetTitleForQuestID(questId)
    end
    return nil
end
