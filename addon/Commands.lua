local WIVBN = WhereIveBeen

SLASH_WHEREIVEBEEN1 = "/whereivebeen"
SLASH_WHEREIVEBEEN2 = "/wivebeen"
SLASH_WHEREIVEBEEN3 = "/wrivbn"

SlashCmdList.WHEREIVEBEEN = function(command)
    command = command:lower():trim()
    if command == "start" then
        WIVBN.StartSession()
    elseif command == "end" then
        WIVBN.EndSession()
    elseif command == "status" then
        WIVBN.ShowSessionStatus()
    elseif command == "clear" then
        WIVBN.ClearSession()
    elseif command == "clear_all" then
        WIVBN.ClearAllSessions()
    elseif command == "log" then
        WIVBN.ShowLog()
    else
        print(WIVBN.PREFIX.."Unknown command. Available commands are: \n- start - create new session, \n- end - close current session and disable working of saving pathes, \n- status - check status of session, \n- clear - clear pathes of current session, \n- log - print last 20 log entries")
    end
end
