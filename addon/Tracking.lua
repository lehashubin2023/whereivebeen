local WIVBN = WhereIveBeen

local DAMAGE_EVENTS = {
    SWING_DAMAGE          = true,
    RANGE_DAMAGE          = true,
    SPELL_DAMAGE          = true,
    SPELL_PERIODIC_DAMAGE = true,
    SPELL_BUILDING_DAMAGE = true,
    ENVIRONMENTAL_DAMAGE  = true,
}

local lastZone, lastZoneMap

function WIVBN.SpellName(spellId)
    if C_Spell and C_Spell.GetSpellInfo then
        local info = C_Spell.GetSpellInfo(spellId)

        return info and info.name or nil
    end

    if GetSpellInfo then
        return (GetSpellInfo(spellId))
    end

    return nil
end

function WIVBN.ResetTracking()
    WIVBN.playerGuid = UnitGUID("player")
    WIVBN.lastHit    = nil
    WIVBN.lastCast   = nil
    WIVBN.gatherNode = nil
    lastZone, lastZoneMap = nil, nil
end

function WIVBN.SaveZoneState()
    if not WIVBN.IsSessionActive() then return end

    local zone    = GetZoneText()
    local subZone = GetSubZoneText()
    local mapId   = C_Map.GetBestMapForUnit("player")

    if not zone or zone == "" then return end
    if zone == lastZone and mapId == lastZoneMap then return end

    lastZone, lastZoneMap = zone, mapId

    WIVBN.RefinePoint(WIVBN.SaveEvent({
        event   = "zone",
        zone    = zone,
        subZone = (subZone and subZone ~= "" and subZone ~= zone) and subZone or nil,
    }))
end

function WIVBN.OnSpellSucceeded(unit, castGuid, spellId)
    if unit ~= "player" or not spellId then return end

    WIVBN.lastCast = {
        id   = spellId,
        name = WIVBN.SpellName(spellId),
        at   = GetTime(),
    }
end

local function LootSourceGuid()
    if not GetLootSourceInfo or not GetNumLootItems then return nil end

    for slot = 1, GetNumLootItems() do
        local guid = GetLootSourceInfo(slot)
        if guid then return guid end
    end

    return nil
end

function WIVBN.OnLootOpened()
    local fishing = IsFishingLoot and IsFishingLoot()
    local guid    = LootSourceGuid()
    local isNode  = guid and guid:sub(1, 10) == "GameObject"

    if not fishing and not isNode then
        WIVBN.gatherNode = nil
        return
    end

    local cast = WIVBN.lastCast
    if cast and (GetTime() - cast.at) > WIVBN.GATHER_WINDOW then cast = nil end

    WIVBN.gatherNode = {
        name      = LootFrameTitleText and LootFrameTitleText:GetText() or nil,
        objectId  = WIVBN.GuidId(guid),
        prof      = fishing and "fishing" or nil,
        spellId   = cast and cast.id or nil,
        spellName = cast and cast.name or nil,
    }
end

function WIVBN.PushLoot(entry)
    if WIVBN.gatherNode then
        entry.node = WIVBN.gatherNode
        WIVBN.PushAggregated("gather", entry)
        return
    end

    WIVBN.PushAggregated("loot", entry)
end

function WIVBN.OnCombatLog()
    if not CombatLogGetCurrentEventInfo then return end

    local _, subEvent, _, sourceGuid, sourceName, _, _, destGuid = CombatLogGetCurrentEventInfo()

    if destGuid ~= WIVBN.playerGuid then return end
    if not DAMAGE_EVENTS[subEvent] then return end

    if subEvent == "ENVIRONMENTAL_DAMAGE" then
        local environment, amount = select(12, CombatLogGetCurrentEventInfo())

        WIVBN.lastHit = { environment = environment, amount = amount, at = GetTime() }

        return
    end

    local hit = {
        name  = sourceName,
        npcId = WIVBN.GuidId(sourceGuid),
        pvp   = (sourceGuid and sourceGuid:sub(1, 6) == "Player") or false,
        at    = GetTime(),
    }

    if subEvent == "SWING_DAMAGE" then
        hit.amount = select(12, CombatLogGetCurrentEventInfo())
    else
        local _, spellName, _, amount = select(12, CombatLogGetCurrentEventInfo())
        hit.spell  = spellName
        hit.amount = amount
    end

    WIVBN.lastHit = hit
end

function WIVBN.KillerPayload()
    local hit = WIVBN.lastHit
    if not hit or (GetTime() - hit.at) > WIVBN.KILLER_WINDOW then return nil end

    if hit.environment then return nil, hit.environment end

    return {
        name   = hit.name,
        npcId  = hit.npcId,
        spell  = hit.spell,
        amount = hit.amount,
        pvp    = hit.pvp,
    }
end

function WIVBN.GapReason()
    if UnitIsDeadOrGhost("player") then return "death" end
    if UnitOnTaxi("player") then return "taxi" end

    local cast = WIVBN.lastCast
    if cast and (GetTime() - cast.at) <= WIVBN.TELEPORT_WINDOW then
        return "spell", cast.id, cast.name
    end

    return "loading"
end
