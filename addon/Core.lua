WhereIveBeen = WhereIveBeen or {}
local WIVBN = WhereIveBeen

WIVBN.sessionId     = nil
WIVBN.timeElapsed   = 0
WIVBN.stateElapsed  = 0
WIVBN.wasMounted    = false
WIVBN.wasDead       = false
WIVBN.groupRoster   = {}
WIVBN.eventHandlers = {}
WIVBN.lastMapId     = nil
WIVBN.lastX         = nil
WIVBN.lastY         = nil
WIVBN.wasOnTaxi     = false
WIVBN.rotating      = false
WIVBN.rawExport     = false

WIVBN.SCHEMA = 2

WIVBN.WRITING_INTERVAL = 20
WIVBN.STATE_INTERVAL   = 0.5
WIVBN.REFINE_DELAY     = 0.5
WIVBN.MIN_MOVE         = 0.005
WIVBN.RESUME_GAP_MIN   = 60
WIVBN.AGGREGATE_WINDOW = 1.0
WIVBN.GATHER_WINDOW    = 3.0
WIVBN.TELEPORT_WINDOW  = 20
WIVBN.KILLER_WINDOW    = 10

WIVBN.MAX_POINTS_SESSION = 0
WIVBN.MAX_POINTS_TOTAL   = 0
WIVBN.MAX_SESSIONS       = 0

WIVBN.PREFIX = "|cffff0000WhereIveBeen|r: "

local eventHandlers = WIVBN.eventHandlers

local function BuildPattern(format)
    if type(format) ~= "string" then return nil end

    local pattern = format:gsub("%%s", "\1"):gsub("%%d", "\2")
    pattern = pattern:gsub("([%^%$%(%)%%%.%[%]%*%+%-%?])", "%%%1")
    pattern = pattern:gsub("\1", "(.+)"):gsub("\2", "(%%d+)")

    return "^" .. pattern
end

local lootPatterns = {
    { pattern = BuildPattern(LOOT_ITEM_SELF_MULTIPLE),        multiple = true },
    { pattern = BuildPattern(LOOT_ITEM_PUSHED_SELF_MULTIPLE), multiple = true },
    { pattern = BuildPattern(LOOT_ITEM_SELF),                 multiple = false },
    { pattern = BuildPattern(LOOT_ITEM_PUSHED_SELF),          multiple = false },
}

local function ParseLootMessage(text)
    for _, entry in ipairs(lootPatterns) do
        if entry.pattern then
            local link, count = text:match(entry.pattern)
            if link then
                return link, entry.multiple and tonumber(count) or 1
            end
        end
    end

    return nil
end

function WIVBN.SafeRegister(frame, event)
    return pcall(frame.RegisterEvent, frame, event)
end

function eventHandlers.ADDON_LOADED(self, addonName)
    if addonName ~= "WhereIveBeen" then return end
    WIVBN.InitDB()
    self:UnregisterEvent("ADDON_LOADED")
end

function eventHandlers.PLAYER_LOGIN(self)
    WIVBN.ResetTracking()
    WIVBN.ResumeOrIdle()
end

function eventHandlers.PLAYER_LOGOUT(self)
    WIVBN.FlushPending()
end

function eventHandlers.UNIT_AURA(self)
    WIVBN.SaveIsMountedState()
end

function eventHandlers.GROUP_ROSTER_UPDATE(self)
    WIVBN.SaveGroupState()
end

function eventHandlers.PLAYER_DEAD(self)
    WIVBN.wasDead = true

    local killer, environment = WIVBN.KillerPayload()

    WIVBN.RefinePoint(WIVBN.SaveEvent({
        event       = "death",
        killer      = killer,
        environment = environment,
    }))
end

function eventHandlers.COMBAT_LOG_EVENT_UNFILTERED(self)
    WIVBN.OnCombatLog()
end

function eventHandlers.UNIT_SPELLCAST_SUCCEEDED(self, unit, castGuid, spellId)
    WIVBN.OnSpellSucceeded(unit, castGuid, spellId)
end

function eventHandlers.ZONE_CHANGED(self)
    WIVBN.SaveZoneState()
end

eventHandlers.ZONE_CHANGED_NEW_AREA = eventHandlers.ZONE_CHANGED
eventHandlers.ZONE_CHANGED_INDOORS  = eventHandlers.ZONE_CHANGED

function eventHandlers.PLAYER_UNGHOST(self)
    WIVBN.OnResurrect()
end

function eventHandlers.PLAYER_ALIVE(self)
    if not UnitIsGhost("player") then
        WIVBN.OnResurrect()
    end
end

function eventHandlers.PLAYER_ENTERING_WORLD(self, isInitialLogin, isReload)
    WIVBN.playerGuid = UnitGUID("player")
    WIVBN.SyncStateFlags()

    if isInitialLogin or isReload then
        WIVBN.SaveZoneState()
        return
    end

    local reason, spellId, spellName = WIVBN.GapReason()

    WIVBN.RefinePoint(WIVBN.SaveEvent({
        event     = "gap",
        reason    = reason,
        spellId   = spellId,
        spellName = spellName,
    }))

    WIVBN.SaveZoneState()
end

function eventHandlers.PLAYER_LEVEL_UP(self, level)
    WIVBN.SaveEvent({ event = "levelup", level = level })
end

function eventHandlers.CHAT_MSG_LOOT(self, text)
    local link, count = ParseLootMessage(text)
    if not link then return end

    local itemId = tonumber(link:match("|Hitem:(%d+):"))
    if not itemId then return end

    WIVBN.PushLoot({
        id   = itemId,
        name = link:match("|h%[(.-)%]|h"),
        n    = count,
    })
end

function eventHandlers.LOOT_OPENED(self)
    WIVBN.OnLootOpened()
end

function eventHandlers.LOOT_CLOSED(self)
    WIVBN.OnLootClosed()
end

function eventHandlers.PLAYER_REGEN_DISABLED(self)
    WIVBN.SaveEvent({ event = "combat", inCombat = true })
end

function eventHandlers.PLAYER_REGEN_ENABLED(self)
    WIVBN.SaveEvent({ event = "combat", inCombat = false })
end

function WIVBN.SaveVisit(place)
    local npcId, npcName = WIVBN.NpcInfo()
    WIVBN.PushAggregated("visit", { place = place, npcId = npcId, npcName = npcName })
end

function eventHandlers.MERCHANT_SHOW(self)
    WIVBN.SaveVisit("merchant")
    if CanMerchantRepair and CanMerchantRepair() then
        WIVBN.SaveVisit("repair")
    end
end

function eventHandlers.BANKFRAME_OPENED(self)
    WIVBN.SaveVisit("bank")
end

function eventHandlers.AUCTION_HOUSE_SHOW(self)
    WIVBN.SaveVisit("auction")
end

function eventHandlers.MAIL_SHOW(self)
    WIVBN.SaveVisit("mail")
end

function eventHandlers.TRAINER_SHOW(self)
    WIVBN.SaveVisit("trainer")
end

function eventHandlers.TAXIMAP_OPENED(self)
    WIVBN.SaveVisit("flightmaster")
end

function eventHandlers.GUILDBANKFRAME_OPENED(self)
    WIVBN.SaveVisit("guildbank")
end

function eventHandlers.PET_STABLE_SHOW(self)
    WIVBN.SaveVisit("stable")
end

function eventHandlers.BARBER_SHOP_OPEN(self)
    WIVBN.SaveVisit("barber")
end

function eventHandlers.TRADE_SHOW(self)
    WIVBN.SaveVisit("trade")
end

function eventHandlers.QUEST_ACCEPTED(self, arg1, arg2)
    local questId = arg2 or arg1
    WIVBN.SaveEvent({ event = "quest", action = "accept", questId = questId, title = WIVBN.QuestTitle(questId) })
end

function eventHandlers.QUEST_TURNED_IN(self, questId)
    WIVBN.SaveEvent({ event = "quest", action = "turnin", questId = questId, title = WIVBN.QuestTitle(questId) })
end

local frame = CreateFrame("Frame")

local events = {
    "ADDON_LOADED",
    "PLAYER_LOGIN",
    "PLAYER_LOGOUT",
    "GROUP_ROSTER_UPDATE",
    "PLAYER_DEAD",
    "PLAYER_ALIVE",
    "PLAYER_UNGHOST",
    "PLAYER_REGEN_DISABLED",
    "PLAYER_REGEN_ENABLED",
    "MERCHANT_SHOW",
    "BANKFRAME_OPENED",
    "AUCTION_HOUSE_SHOW",
    "CHAT_MSG_LOOT",
    "LOOT_OPENED",
    "LOOT_CLOSED",
    "PLAYER_LEVEL_UP",
    "PLAYER_ENTERING_WORLD",
    "QUEST_ACCEPTED",
    "QUEST_TURNED_IN",
    "MAIL_SHOW",
    "TRAINER_SHOW",
    "TAXIMAP_OPENED",
    "GUILDBANKFRAME_OPENED",
    "PET_STABLE_SHOW",
    "BARBER_SHOP_OPEN",
    "TRADE_SHOW",
    "ZONE_CHANGED",
    "ZONE_CHANGED_NEW_AREA",
    "ZONE_CHANGED_INDOORS",
    "COMBAT_LOG_EVENT_UNFILTERED",
}

for _, event in ipairs(events) do
    WIVBN.SafeRegister(frame, event)
end

frame:RegisterUnitEvent("UNIT_AURA", "player")
pcall(frame.RegisterUnitEvent, frame, "UNIT_SPELLCAST_SUCCEEDED", "player")

frame:SetScript("OnEvent", function(self, event, ...)
    local h = eventHandlers[event]
    if h then h(self, ...) end
end)

frame:SetScript("OnUpdate", function(self, elapsed)
    WIVBN.stateElapsed = WIVBN.stateElapsed + elapsed
    if WIVBN.stateElapsed >= WIVBN.STATE_INTERVAL then
        WIVBN.stateElapsed = 0
        WIVBN.SaveTaxiState()
        WIVBN.CheckPendingPosition()
    end

    if WIVBN.AccumulateInterval(elapsed) >= WIVBN.WRITING_INTERVAL then
        WIVBN.ClearInterval()
        WIVBN.SaveTimedPosition()
    end
end)
