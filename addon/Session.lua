local WIVBN = WhereIveBeen

function WIVBN.IsSessionActive()
    return WIVBN.sessionId ~= nil
end

function WIVBN.ShowSessionStatus()
    local status = "unactive"
    if WIVBN.IsSessionActive() then
        status = "active"
    end
    print(WIVBN.PREFIX.."Session is "..status)
end

function WIVBN.StartSession()
    if WIVBN.IsSessionActive() then
        print(WIVBN.PREFIX.."Current session already started. End or clear current session before")
        return nil
    end

    local id = time()
    while WhereIveBeenDB.sessions[id] do   -- ключ должен быть уникальным (несколько стартов в одну секунду)
        id = id + 1
    end
    WIVBN.sessionId = id
    WhereIveBeenDB.sessions[id] = {
        started = time(),
        clock   = GetTime(),
        char    = UnitName("player"),
        realm   = GetRealmName(),
        points  = {},
    }

    WIVBN.lastMapID, WIVBN.lastX, WIVBN.lastY = nil, nil, nil

    print(WIVBN.PREFIX.."Session successfully started")
end

function WIVBN.EndSession()
    if not WIVBN.IsSessionActive() then
        print(WIVBN.PREFIX.."Session wasn`t created")
        return nil
    end

    WIVBN.sessionId = nil

    print(WIVBN.PREFIX.."Session successfully ended")
end

function WIVBN.ClearSession()
    if not WIVBN.IsSessionActive() then
        print(WIVBN.PREFIX.."Session wasn`t created")
        return nil
    end

    WhereIveBeenDB.sessions[WIVBN.sessionId].points = {}

    print(WIVBN.PREFIX.."Session successfully clean")
end

function WIVBN.ClearAllSessions()
    WIVBN.sessionId = nil
    WhereIveBeenDB.sessions = {}

    print(WIVBN.PREFIX.."Sessions successfully clean")
end

-- LOG OUTPUT

function WIVBN.GetCurrentOrLastSession()
    if WIVBN.sessionId and WhereIveBeenDB.sessions[WIVBN.sessionId] then
        return WhereIveBeenDB.sessions[WIVBN.sessionId]
    end

    local lastId                                   -- нет активной — берём самую свежую (id = time())
    for id in pairs(WhereIveBeenDB.sessions) do
        if not lastId or id > lastId then lastId = id end
    end
    return lastId and WhereIveBeenDB.sessions[lastId] or nil
end

function WIVBN.DescribePoint(p)
    local e = p.event

    if e == "mount"     then return p.mounted and "сел на маунта" or "слез с маунта" end
    if e == "combat"    then return p.inCombat and "|cffff5555бой начался|r" or "бой окончен" end
    if e == "death"     then return "|cffff0000смерть|r" end
    if e == "resurrect" then return "|cff00ff00воскрешение|r" end
    if e == "levelup"   then return "уровень "..tostring(p.level) end
    if e == "loot"      then return ("лут: %s x%s"):format(p.itemName or tostring(p.itemId), tostring(p.count or 1)) end
    if e == "visit"     then return "визит: "..tostring(p.place) end
    if e == "group"     then return ("группа: %s %s"):format(tostring(p.action), tostring(p.member)) end
    if e == "quest"     then return ("квест: %s %s"):format(tostring(p.action), p.title or tostring(p.questID)) end
    if e == "taxi"      then return p.onTaxi and "взлёт (такси)" or "посадка (такси)" end
    if e == "gap"       then return "|cffff8800разрыв маршрута|r" end

    if p.IsMounted ~= nil then return p.IsMounted and "путь (верхом)" or "путь (пеший)" end  -- легаси

    return "путь"
end

function WIVBN.FormatPoint(p)
    local info = C_Map.GetMapInfo(p.mapID)
    local zone = (info and info.name) or ("map "..tostring(p.mapID))
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

    local limit = 20                               -- последние 20 записей
    local from  = math.max(1, total - limit + 1)

    print(WIVBN.PREFIX..("last %d of %d points:"):format(total - from + 1, total))
    for i = from, total do
        print(WIVBN.FormatPoint(points[i]))
    end
end
