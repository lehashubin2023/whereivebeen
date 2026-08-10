local WIVBN = WhereIveBeen

local ROW_HEIGHT = 22
local NUM_ROWS   = 12

local listFrame    -- окно со списком сессий (создаётся лениво)
local exportFrame  -- окно экспорта base64 (создаётся лениво)
local sessionIds   -- отсортированный массив id, кэш на время показа списка

-- COMMON: рамочное окно с заголовком, кнопкой закрытия и закрытием по Esc

local function CreateWindow(name, width, height, title)
    local f = CreateFrame("Frame", name, UIParent, "BackdropTemplate")
    f:SetSize(width, height)
    f:SetPoint("CENTER")
    f:SetFrameStrata("DIALOG")
    f:SetBackdrop({
        bgFile   = "Interface\\DialogFrame\\UI-DialogBox-Background",
        edgeFile = "Interface\\DialogFrame\\UI-DialogBox-Border",
        tile = true, tileSize = 32, edgeSize = 32,
        insets = { left = 11, right = 12, top = 12, bottom = 11 },
    })
    f:SetMovable(true)
    f:EnableMouse(true)
    f:RegisterForDrag("LeftButton")
    f:SetScript("OnDragStart", f.StartMoving)
    f:SetScript("OnDragStop", f.StopMovingOrSizing)
    f:SetClampedToScreen(true)

    tinsert(UISpecialFrames, name)   -- Esc закрывает окно

    local titleFS = f:CreateFontString(nil, "OVERLAY", "GameFontNormalLarge")
    titleFS:SetPoint("TOP", 0, -16)
    titleFS:SetText(title)

    local close = CreateFrame("Button", nil, f, "UIPanelCloseButton")
    close:SetPoint("TOPRIGHT", -4, -4)

    f:Hide()
    return f
end

-- SESSIONS LIST WINDOW

local function CollectSessionIds()
    local ids = {}
    local sessions = WhereIveBeenDB and WhereIveBeenDB.sessions or {}
    for id in pairs(sessions) do
        ids[#ids + 1] = id
    end
    table.sort(ids, function(a, b) return a > b end)   -- свежие сверху (id = time())
    return ids
end

local function SessionRowLabel(id)
    local s    = WhereIveBeenDB.sessions[id]
    local when = date("%Y-%m-%d %H:%M", s.started or id)
    local who  = (s.char or "?") .. "-" .. (s.realm or "?")
    local n    = s.points and #s.points or 0
    return ("%s  |cffffd100%s|r  |cffaaaaaa(%d pts)|r"):format(when, who, n)
end

local function UpdateList()
    local scroll = listFrame.scroll
    local offset = FauxScrollFrame_GetOffset(scroll)
    local total  = #sessionIds

    for i = 1, NUM_ROWS do
        local row   = listFrame.rows[i]
        local index = offset + i
        if index <= total then
            row.id = sessionIds[index]
            row.text:SetText(SessionRowLabel(row.id))
            row:Show()
        else
            row.id = nil
            row:Hide()
        end
    end

    FauxScrollFrame_Update(scroll, total, NUM_ROWS, ROW_HEIGHT)
end

local function CreateListFrame()
    local f = CreateWindow("WhereIveBeenSessionsFrame",
        440, NUM_ROWS * ROW_HEIGHT + 74, "WhereIveBeen — сессии")

    local scroll = CreateFrame("ScrollFrame", "$parentScroll", f, "FauxScrollFrameTemplate")
    scroll:SetPoint("TOPLEFT", 16, -48)
    scroll:SetPoint("BOTTOMRIGHT", -34, 16)
    scroll:SetScript("OnVerticalScroll", function(self, offset)
        FauxScrollFrame_OnVerticalScroll(self, offset, ROW_HEIGHT, UpdateList)
    end)
    f.scroll = scroll

    f.rows = {}
    for i = 1, NUM_ROWS do
        local row = CreateFrame("Button", nil, f)
        row:SetHeight(ROW_HEIGHT)
        row:SetPoint("TOPLEFT",  scroll, "TOPLEFT",  0, -(i - 1) * ROW_HEIGHT)
        row:SetPoint("TOPRIGHT", scroll, "TOPRIGHT", 0, -(i - 1) * ROW_HEIGHT)

        local hl = row:CreateTexture(nil, "HIGHLIGHT")
        hl:SetAllPoints()
        hl:SetColorTexture(1, 1, 1, 0.15)

        local fs = row:CreateFontString(nil, "OVERLAY", "GameFontHighlightSmall")
        fs:SetPoint("LEFT", 4, 0)
        fs:SetPoint("RIGHT", -4, 0)
        fs:SetJustifyH("LEFT")
        row.text = fs

        row:SetScript("OnClick", function(self)
            if self.id then WIVBN.ShowExportWindow(self.id) end
        end)

        f.rows[i] = row
    end

    return f
end

function WIVBN.ShowSessionsWindow()
    sessionIds = CollectSessionIds()
    if #sessionIds == 0 then
        print(WIVBN.PREFIX .. "No sessions to show")
        return
    end

    if not listFrame then
        listFrame = CreateListFrame()
    end

    FauxScrollFrame_SetOffset(listFrame.scroll, 0)
    listFrame.scroll:SetVerticalScroll(0)
    UpdateList()
    listFrame:Show()
    listFrame:Raise()
end

-- EXPORT WINDOW (base64)

-- Программная установка текста без срабатывания «защиты от правки».
local function SetExportText(edit, text)
    edit.wivbnSetting = true
    edit:SetText(text)
    edit.wivbnText = text
    edit.wivbnSetting = false
end

local function CreateExportFrame()
    local f = CreateWindow("WhereIveBeenExportFrame", 560, 420,
        "WhereIveBeen — экспорт (base64)")

    local hint = f:CreateFontString(nil, "OVERLAY", "GameFontHighlightSmall")
    hint:SetPoint("BOTTOM", 0, 18)
    hint:SetText("Ctrl+C — скопировать, Esc — закрыть")

    local scroll = CreateFrame("ScrollFrame", "$parentScroll", f, "UIPanelScrollFrameTemplate")
    scroll:SetPoint("TOPLEFT", 16, -48)
    scroll:SetPoint("BOTTOMRIGHT", -32, 40)

    local edit = CreateFrame("EditBox", nil, scroll)
    edit:SetMultiLine(true)
    edit:SetAutoFocus(false)
    edit:SetFontObject(ChatFontNormal)
    edit:SetWidth(496)
    edit:SetScript("OnEscapePressed", function() f:Hide() end)
    edit:SetScript("OnEditFocusGained", function(self) self:HighlightText() end)

    -- Read-only-поведение: любой пользовательский ввод откатываем к исходному base64,
    -- чтобы строка для копирования всегда оставалась целой.
    edit:SetScript("OnTextChanged", function(self, userInput)
        if userInput and not self.wivbnSetting and self.wivbnText then
            self.wivbnSetting = true
            self:SetText(self.wivbnText)
            self:HighlightText()
            self.wivbnSetting = false
        end
    end)

    scroll:SetScrollChild(edit)
    f.edit = edit

    return f
end

function WIVBN.ShowExportWindow(id)
    local data, err = WIVBN.ExportSession(id)
    if not data then
        print(WIVBN.PREFIX .. "Export failed: " .. tostring(err))
        return
    end

    if not exportFrame then
        exportFrame = CreateExportFrame()
    end

    SetExportText(exportFrame.edit, data)
    exportFrame:Show()
    exportFrame:Raise()
    exportFrame.edit:SetCursorPosition(0)
    exportFrame.edit:SetFocus()
    exportFrame.edit:HighlightText()
end
