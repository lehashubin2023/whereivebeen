local WIVBN = WhereIveBeen

local ROW_HEIGHT = 22
local NUM_ROWS   = 12

local listFrame
local exportFrame
local sessionIds

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

    tinsert(UISpecialFrames, name)

    local titleFS = f:CreateFontString(nil, "OVERLAY", "GameFontNormalLarge")
    titleFS:SetPoint("TOP", 0, -16)
    titleFS:SetText(title)

    local close = CreateFrame("Button", nil, f, "UIPanelCloseButton")
    close:SetPoint("TOPRIGHT", -4, -4)

    f:Hide()
    return f
end

local function CollectSessionIds()
    local ids = {}
    local sessions = WhereIveBeenDB and WhereIveBeenDB.sessions or {}
    for id, session in pairs(sessions) do
        if session.points and #session.points > 0 then
            ids[#ids + 1] = id
        end
    end
    table.sort(ids, function(a, b) return a > b end)
    return ids
end

local function SessionRowLabel(id)
    local s    = WhereIveBeenDB.sessions[id]
    local when = date("%Y-%m-%d %H:%M", s.started or 0)
    local who  = (s.char or "?") .. "-" .. (s.realm or "?")
    local n    = s.points and #s.points or 0

    local marks = ""
    if s.continuesFrom then marks = marks .. " |cff88bbffcont|r" end
    if s.exportedAt then marks = marks .. " |cff88ff88exported|r" end

    return ("%s  |cffffd100%s|r  |cffaaaaaa(%d pts)|r%s"):format(when, who, n, marks)
end

StaticPopupDialogs["WHEREIVEBEEN_DELETE_SESSION"] = {
    text = "Delete this session?\n%s",
    button1 = YES,
    button2 = NO,
    timeout = 0,
    whileDead = true,
    hideOnEscape = true,
    preferredIndex = 3,
    OnAccept = function(self, id)
        WIVBN.DeleteSession(id)
        WIVBN.ShowSessionsWindow()
    end,
}

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
        440, NUM_ROWS * ROW_HEIGHT + 74, "WhereIveBeen — Sessions")

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

        row:RegisterForClicks("LeftButtonUp", "RightButtonUp")
        row:SetScript("OnClick", function(self, button)
            if not self.id then return end

            if button == "RightButton" then
                StaticPopup_Show("WHEREIVEBEEN_DELETE_SESSION", SessionRowLabel(self.id), nil, self.id)
                return
            end

            WIVBN.ShowExportWindow(self.id)
        end)

        f.rows[i] = row
    end

    return f
end

function WIVBN.ShowSessionsWindow()
    sessionIds = CollectSessionIds()
    if #sessionIds == 0 then
        if listFrame then listFrame:Hide() end
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

local function SetExportText(edit, text)
    edit.wivbnSetting = true
    edit:SetText(text)
    edit.wivbnText = text
    edit.wivbnSetting = false
end

local function CreateExportFrame()
    local f = CreateWindow("WhereIveBeenExportFrame", 560, 420,
        "WhereIveBeen — Export")

    local hint = f:CreateFontString(nil, "OVERLAY", "GameFontHighlightSmall")
    hint:SetPoint("BOTTOM", 0, 18)
    hint:SetText("Ctrl+C to copy, Esc to close")
    f.hint = hint

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
    local data, err, note = WIVBN.ExportSession(id)
    if not data then
        print(WIVBN.PREFIX .. "Export failed: " .. tostring(err))
        return
    end

    if not exportFrame then
        exportFrame = CreateExportFrame()
    end

    SetExportText(exportFrame.edit, data)
    exportFrame.hint:SetText(("%s, %d chars  |cffaaaaaa Ctrl+C to copy, Esc to close|r")
        :format(note or "compressed", #data))

    if note and note ~= "raw JSON" then
        print(WIVBN.PREFIX .. "|cffff8800" .. note .. "|r")
    end

    exportFrame:Show()
    exportFrame:Raise()
    exportFrame.edit:SetCursorPosition(0)
    exportFrame.edit:SetFocus()
    exportFrame.edit:HighlightText()
end
