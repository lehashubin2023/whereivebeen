WhereIveBeen = {}                 -- глобальный контейнер аддона (вариант В)
local WIVBN = WhereIveBeen        -- локальный алиас

-- STATE

WIVBN.sessionId     = nil
WIVBN.timeElapsed   = 0
WIVBN.wasMounted    = false
WIVBN.wasDead       = false
WIVBN.groupRoster   = {}
WIVBN.eventHandlers = {}
WIVBN.lastMapID     = nil         -- последняя записанная позиция (для фильтра движения)
WIVBN.lastX         = nil
WIVBN.lastY         = nil
WIVBN.wasOnTaxi     = false       -- для отслеживания начала/конца полёта

-- CONSTANTS

WIVBN.WRITING_INTERVAL = 20       -- секунд между периодическими точками
WIVBN.MIN_MOVE         = 0.005    -- мин. смещение (координаты 0..1) для новой периодической точки

WIVBN.PREFIX = "|cffff0000WhereIveBeen|r: "

-- EVENT HANDLERS

local eventHandlers = WIVBN.eventHandlers

function eventHandlers.ADDON_LOADED(self, addonName)
    if addonName ~= "WhereIveBeen" then return end
    WIVBN.InitDB()
    WIVBN.StartSession()
    self:UnregisterEvent("ADDON_LOADED")
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
    WIVBN.wasDead    = UnitIsDeadOrGhost("player") and true or false
    WIVBN.wasMounted = IsMounted() and true or false            -- сидинг, чтобы вход верхом не дал ложный маркер
    WIVBN.wasOnTaxi  = UnitOnTaxi("player") and true or false   -- то же для входа в полёте

    if not isInitialLogin and not isReload then
        WIVBN.SaveEvent({ event = "gap" })   -- только реальный переход: портал, хартстоун, инст, лодка (не логин/reload)
    end
end

function eventHandlers.PLAYER_LEVEL_UP(self, level)
    WIVBN.SaveEvent({ event = "levelup", level = level })
end

function eventHandlers.CHAT_MSG_LOOT(self, text)
    if not text:find("You receive") then return end

    local itemId   = tonumber(text:match("|Hitem:(%d+):"))
    local itemName = text:match("|h%[(.-)%]|h")
    local count    = tonumber(text:match("|hx(%d+)")) or 1
    if not itemId then return end

    WIVBN.SaveEvent({
        event    = "loot",
        itemId   = itemId,
        itemName = itemName,
        count    = count,
    })
end

-- Бой/такси пишутся парами (start/end). На границе сессий пара может разойтись
-- (start в одной сессии, end в другой) — это ожидаемо. Вариант A: рендер достраивает
-- непарные (висячий start закрывается концом сессии, висячий end — её началом).
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
    local questID = arg2 or arg1                       -- payload зависит от версии клиента
    WIVBN.SaveEvent({ event = "quest", action = "accept", questID = questID, title = WIVBN.QuestTitle(questID) })
end

function eventHandlers.QUEST_TURNED_IN(self, questID)
    WIVBN.SaveEvent({ event = "quest", action = "turnin", questID = questID, title = WIVBN.QuestTitle(questID) })
end

function eventHandlers.PLAYER_CONTROL_LOST(self)
    WIVBN.SaveTaxiState()
end

function eventHandlers.PLAYER_CONTROL_GAINED(self)
    WIVBN.SaveTaxiState()
end

-- FRAME, REGISTRATION, DISPATCH

local frame = CreateFrame("Frame")

frame:RegisterEvent("ADDON_LOADED")
frame:RegisterEvent("GROUP_ROSTER_UPDATE")
frame:RegisterEvent("PLAYER_DEAD")
frame:RegisterEvent("PLAYER_ALIVE")
frame:RegisterEvent("PLAYER_UNGHOST")
frame:RegisterEvent("PLAYER_REGEN_DISABLED")
frame:RegisterEvent("PLAYER_REGEN_ENABLED")
frame:RegisterEvent("MERCHANT_SHOW")
frame:RegisterEvent("BANKFRAME_OPENED")
frame:RegisterEvent("AUCTION_HOUSE_SHOW")
frame:RegisterEvent("CHAT_MSG_LOOT")
frame:RegisterEvent("PLAYER_LEVEL_UP")
frame:RegisterEvent("PLAYER_ENTERING_WORLD")
frame:RegisterEvent("QUEST_ACCEPTED")
frame:RegisterEvent("QUEST_TURNED_IN")
frame:RegisterEvent("PLAYER_CONTROL_LOST")
frame:RegisterEvent("PLAYER_CONTROL_GAINED")
frame:RegisterUnitEvent("UNIT_AURA", "player")

frame:SetScript("OnEvent", function(self, event, ...)
    local h = eventHandlers[event]
    if h then h(self, ...) end
end)

frame:SetScript("OnUpdate", function(self, elapsed)
    if WIVBN.AccumulateInterval(elapsed) >= WIVBN.WRITING_INTERVAL then
        WIVBN.ClearInterval()
        WIVBN.SaveTimedPosition()
    end
end)
