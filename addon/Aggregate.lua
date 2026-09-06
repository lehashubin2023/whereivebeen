local WIVBN = WhereIveBeen

local generation = 0

local aggregators = {}

aggregators.loot = {
    window = WIVBN.AGGREGATE_WINDOW,
    init   = function() return { items = {} } end,
    add    = function(data, entry)
        for _, item in ipairs(data.items) do
            if item.id == entry.id then
                item.n = item.n + (entry.n or 1)
                return
            end
        end

        data.items[#data.items + 1] = { id = entry.id, name = entry.name, n = entry.n or 1 }
    end,
    build  = function(data)
        return { event = "loot", items = data.items }
    end,
}

aggregators.gather = {
    window = WIVBN.GATHER_WINDOW,
    init   = function() return { items = {} } end,
    add    = function(data, entry)
        data.node = data.node or entry.node

        if not entry.id then return end

        for _, item in ipairs(data.items) do
            if item.id == entry.id then
                item.n = item.n + (entry.n or 1)
                return
            end
        end

        data.items[#data.items + 1] = { id = entry.id, name = entry.name, n = entry.n or 1 }
    end,
    build  = function(data)
        return { event = "gather", node = data.node, items = data.items }
    end,
}

aggregators.group = {
    window = WIVBN.AGGREGATE_WINDOW,
    init   = function() return { joined = {}, left = {} } end,
    add    = function(data, entry)
        local list = entry.action == "join" and data.joined or data.left
        list[#list + 1] = entry.member
    end,
    build  = function(data)
        return {
            event  = "group",
            joined = #data.joined > 0 and data.joined or nil,
            left   = #data.left > 0 and data.left or nil,
        }
    end,
}

aggregators.visit = {
    window = WIVBN.AGGREGATE_WINDOW,
    init   = function() return { places = {}, seen = {} } end,
    add    = function(data, entry)
        if not data.seen[entry.place] then
            data.seen[entry.place] = true
            data.places[#data.places + 1] = entry.place
        end

        data.npcId   = data.npcId or entry.npcId
        data.npcName = data.npcName or entry.npcName
    end,
    build  = function(data)
        return {
            event   = "visit",
            places  = data.places,
            npcId   = data.npcId,
            npcName = data.npcName,
        }
    end,
}

WIVBN.aggregators = aggregators

local function Anchor()
    local mapId, x, y = WIVBN.GetPlayerPosition()

    return {
        mapId = mapId,
        x     = x,
        y     = y,
        t     = WIVBN.SessionTime(WIVBN.CurrentSession()),
    }
end

local function Arm(window)
    generation = generation + 1
    local mine = generation

    C_Timer.After(window, function()
        if generation == mine then WIVBN.FlushPending() end
    end)
end

function WIVBN.PushAggregated(kind, entry)
    if not WIVBN.IsSessionActive() then return nil end

    local aggregator = aggregators[kind]
    if not aggregator then return nil end

    if WIVBN.pending and WIVBN.pending.kind ~= kind then
        WIVBN.FlushPending()
    end

    if WIVBN.pending and (GetTime() - (WIVBN.pending.at or 0)) >= WIVBN.AGGREGATE_MAX then
        WIVBN.FlushPending()
    end

    if not WIVBN.pending then
        WIVBN.pending = { kind = kind, anchor = Anchor(), data = aggregator.init(), at = GetTime() }
    end

    aggregator.add(WIVBN.pending.data, entry)
    Arm(aggregator.window)
end

function WIVBN.ReclassifyPending(fromKind, toKind, transform)
    local pending = WIVBN.pending
    if not pending or pending.kind ~= fromKind then return false end

    local aggregator = aggregators[toKind]
    if not aggregator then return false end

    if (GetTime() - (pending.at or 0)) > WIVBN.ADOPT_WINDOW then return false end

    pending.kind = toKind
    pending.data = transform(pending.data)

    Arm(aggregator.window)

    return true
end

function WIVBN.FlushPending()
    local pending = WIVBN.pending
    if not pending then return nil end

    WIVBN.pending = nil
    generation = generation + 1

    if not WIVBN.IsSessionActive() then return nil end

    local extra  = aggregators[pending.kind].build(pending.data)
    local anchor = pending.anchor

    extra.t = anchor.t

    local point = WIVBN.WritePoint(anchor.mapId, anchor.x, anchor.y, extra)
    WIVBN.ClearInterval()
    WIVBN.RefinePoint(point)

    return point
end

function WIVBN.FlushKind(kind)
    if WIVBN.pending and WIVBN.pending.kind == kind then
        WIVBN.FlushPending()
    end
end

function WIVBN.CheckPendingPosition()
    local pending = WIVBN.pending
    if not pending or not pending.anchor.mapId or not pending.anchor.x then return end

    local mapId, x, y = WIVBN.GetPlayerPosition()
    if not mapId then return end

    if mapId ~= pending.anchor.mapId then
        WIVBN.FlushPending()
        return
    end

    local dx, dy = x - pending.anchor.x, y - pending.anchor.y
    if (dx * dx + dy * dy) >= (WIVBN.MIN_MOVE * WIVBN.MIN_MOVE) then
        WIVBN.FlushPending()
    end
end
