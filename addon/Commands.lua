local WIVBN = WhereIveBeen

SLASH_WHEREIVEBEEN1 = "/whereivebeen"
SLASH_WHEREIVEBEEN2 = "/wivebeen"
SLASH_WHEREIVEBEEN3 = "/wrivbn"

local HELP = {
    "start - start a new session",
    "end - close the current session and stop recording",
    "status - show session state and stored point count",
    "clear - drop the points of the current session",
    "clear_all - drop every stored session",
    "delete <id> - delete one session by id",
    "prune <points> - delete every stored session with fewer points",
    "log - print the last 20 recorded points",
    "get_sessions - open the sessions window and export a session",
    "raw - toggle raw JSON export instead of the compressed one",
    "help - show this list",
}

local function ShowHelp()
    print(WIVBN.PREFIX .. "commands:")
    for _, line in ipairs(HELP) do
        print("  |cffffd100/wivebeen|r " .. line)
    end
end

local function ToggleRaw()
    WIVBN.rawExport = not WIVBN.rawExport
    print(WIVBN.PREFIX .. "Raw JSON export " .. (WIVBN.rawExport and "enabled" or "disabled"))
end

local function DeleteById(argument)
    local id = tonumber(argument)
    if not id then
        print(WIVBN.PREFIX .. "Usage: /wivebeen delete <id>")
        return
    end

    if WIVBN.DeleteSession(id) then
        print(WIVBN.PREFIX .. ("Session %d deleted"):format(id))
    else
        print(WIVBN.PREFIX .. ("Session %d not found"):format(id))
    end
end

local function PruneSmall(argument)
    local minPoints = tonumber(argument)
    if not minPoints or minPoints < 1 then
        print(WIVBN.PREFIX .. "Usage: /wivebeen prune <points> - deletes sessions with fewer points")
        return
    end

    local removed, kept = WIVBN.PruneSessions(minPoints)

    print(WIVBN.PREFIX .. ("Deleted %d sessions under %d points, %d left")
        :format(removed, minPoints, kept))
end

SlashCmdList.WHEREIVEBEEN = function(input)
    local command, argument = (input or ""):lower():trim():match("^(%S*)%s*(.-)$")

    if command == "" or command == "help" then
        ShowHelp()
    elseif command == "start" then
        WIVBN.StartSession()
    elseif command == "end" then
        WIVBN.EndSession()
    elseif command == "status" then
        WIVBN.ShowSessionStatus()
    elseif command == "clear" then
        WIVBN.ClearSession()
    elseif command == "clear_all" then
        WIVBN.ClearAllSessions()
    elseif command == "delete" then
        DeleteById(argument)
    elseif command == "prune" then
        PruneSmall(argument)
    elseif command == "log" then
        WIVBN.ShowLog()
    elseif command == "get_sessions" then
        WIVBN.ShowSessionsWindow()
    elseif command == "raw" then
        ToggleRaw()
    else
        print(WIVBN.PREFIX .. ("Unknown command: %s. Type /wivebeen help"):format(command))
    end
end
