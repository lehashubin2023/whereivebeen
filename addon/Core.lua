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

WIVBN.SCHEMA = 2

WIVBN.WRITING_INTERVAL = 20
WIVBN.STATE_INTERVAL   = 0.5
WIVBN.REFINE_DELAY     = 0.5
WIVBN.MIN_MOVE         = 0.005
WIVBN.RESUME_GAP_MIN   = 60

WIVBN.MAX_POINTS_SESSION = 10000
WIVBN.MAX_POINTS_TOTAL   = 40000
WIVBN.MAX_SESSIONS       = 50

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
    WIVBN.SaveEvent({ event = "death" })
end

function eventHandlers.PLAYER_UNGHOST(self)
    WIVBN.OnResurrect()
end

function eventHandlers.PLAYER_ALIVE(self)
    if not UnitIsGhost("player") then
        WIVBN.OnResurrect()
    end
end

function eventHandlers.PLAYER_ENTERING_WORLD(self, isInitialLogin, isReload)
    WIVBN.wasDead     = UnitIsDeadOrGhost("player") and true or false
    WIVBN.wasMounted  = IsMounted() and true or false
    WIVBN.wasOnTaxi   = UnitOnTaxi("player") and true or false
    WIVBN.groupRoster = WIVBN.GetGroupRoster()

    if isInitialLogin or isReload then return end

    WIVBN.RefinePoint(WIVBN.SaveEvent({ event = "gap" }))
end

function eventHandlers.PLAYER_LEVEL_UP(self, level)
    WIVBN.SaveEvent({ event = "levelup", level = level })
end

function eventHandlers.CHAT_MSG_LOOT(self, text)
    local link, count = ParseLootMessage(text)
    if not link then return end

    local itemId = tonumber(link:match("|Hitem:(%d+):"))
    if not itemId then return end

    WIVBN.SaveEvent({
        event    = "loot",
        itemId   = itemId,
        itemName = link:match("|h%[(.-)%]|h"),
        count    = count,
    })
end

function eventHandlers.PLAYER_REGEN_DISABLED(self)
    WIVBN.SaveEvent({ event = "combat", inCombat = true })
end

function eventHandlers.PLAYER_REGEN_ENABLED(self)
    WIVBN.SaveEvent({ event = "combat", inCombat = false })
end

function eventHandlers.MERCHANT_SHOW(self)
    WIVBN.SaveEvent({ event = "visit", place = "merchant" })
end

function eventHandlers.BANKFRAME_OPENED(self)
    WIVBN.SaveEvent({ event = "visit", place = "bank" })
end

function eventHandlers.AUCTION_HOUSE_SHOW(self)
    WIVBN.SaveEvent({ event = "visit", place = "auction" })
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
    "PLAYER_LEVEL_UP",
    "PLAYER_ENTERING_WORLD",
    "QUEST_ACCEPTED",
    "QUEST_TURNED_IN",
}

for _, event in ipairs(events) do
    WIVBN.SafeRegister(frame, event)
end

frame:RegisterUnitEvent("UNIT_AURA", "player")

frame:SetScript("OnEvent", function(self, event, ...)
    local h = eventHandlers[event]
    if h then h(self, ...) end
end)

frame:SetScript("OnUpdate", function(self, elapsed)
    WIVBN.stateElapsed = WIVBN.stateElapsed + elapsed
    if WIVBN.stateElapsed >= WIVBN.STATE_INTERVAL then
        WIVBN.stateElapsed = 0
        WIVBN.SaveTaxiState()
    end

    if WIVBN.AccumulateInterval(elapsed) >= WIVBN.WRITING_INTERVAL then
        WIVBN.ClearInterval()
        WIVBN.SaveTimedPosition()
    end
end)
