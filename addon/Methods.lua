local WIVBN = WhereIveBeen

function WIVBN.InitDB()
    WhereIveBeenDB = WhereIveBeenDB or {}
    WhereIveBeenDB.sessions = WhereIveBeenDB.sessions or {}
end

function WIVBN.AccumulateInterval(elapsed)
    WIVBN.timeElapsed = WIVBN.timeElapsed + elapsed
    return WIVBN.timeElapsed
end

function WIVBN.ClearInterval()
    WIVBN.timeElapsed = 0
end

function WIVBN.GetPlayerPosition()
    local mapId = C_Map.GetBestMapForUnit("player")
    if not mapId then return nil end

    local position = C_Map.GetPlayerMapPosition(mapId, "player")
    if not position then return nil end

    local x, y = position:GetXY()
    if x == nil or y == nil or (x == 0 and y == 0) then return nil end

    return mapId, x, y
end

function WIVBN.SavePosition(extra)
    if not WIVBN.IsSessionActive() then return nil end

    local mapId, x, y = WIVBN.GetPlayerPosition()
    if not mapId then return nil end

    local session = WhereIveBeenDB.sessions[WIVBN.sessionId]

    local point = {
        x = x,
        y = y,
        mapId = mapId,
        t = math.floor((GetTime() - session.clock) * 10) / 10,
    }
    if extra then
        for k, v in pairs(extra) do point[k] = v end
    end

    table.insert(session.points, point)

    WIVBN.lastMapId, WIVBN.lastX, WIVBN.lastY = mapId, x, y
end

function WIVBN.SaveEvent(extra)
    WIVBN.SavePosition(extra)
    WIVBN.ClearInterval()
end

function WIVBN.SaveTimedPosition()
    if not WIVBN.IsSessionActive() then return nil end

    local mapId, x, y = WIVBN.GetPlayerPosition()
    if not mapId then return nil end

    if WIVBN.lastMapId == mapId and WIVBN.lastX then
        local dx, dy = x - WIVBN.lastX, y - WIVBN.lastY
        if (dx * dx + dy * dy) < (WIVBN.MIN_MOVE * WIVBN.MIN_MOVE) then
            return nil
        end
    end

    WIVBN.SavePosition()
end

function WIVBN.SaveIsMountedState()
    local isMounted = IsMounted()
    if isMounted ~= WIVBN.wasMounted then
        WIVBN.wasMounted = isMounted
        WIVBN.SaveEvent({ event = "mount", mounted = isMounted })
    end
end

function WIVBN.SaveTaxiState()
    local onTaxi = UnitOnTaxi("player") and true or false
    if onTaxi ~= WIVBN.wasOnTaxi then
        WIVBN.wasOnTaxi = onTaxi
        WIVBN.SaveEvent({ event = "taxi", onTaxi = onTaxi })
    end
end

function WIVBN.GetGroupRoster()
    local roster = {}
    if IsInGroup() then
        roster[UnitName("player")] = true
        for i = 1, GetNumSubgroupMembers() do
            local name = UnitName("party"..i)
            if name then roster[name] = true end
        end
    end
    return roster
end

function WIVBN.SaveGroupState()
    local current = WIVBN.GetGroupRoster()

    for name in pairs(current) do
        if not WIVBN.groupRoster[name] then
            WIVBN.SaveEvent({ event = "group", action = "join", member = name })
        end
    end
    for name in pairs(WIVBN.groupRoster) do
        if not current[name] then
            WIVBN.SaveEvent({ event = "group", action = "leave", member = name })
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
