local WIVBN = WhereIveBeen

function WIVBN.BuildSessionExport(id)
    local session = WhereIveBeenDB and WhereIveBeenDB.sessions and WhereIveBeenDB.sessions[id]
    if not session then return nil end

    return {
        version    = select(4, GetBuildInfo()),
        sessionId  = id,
        started    = session.started,
        char       = session.char,
        realm      = session.realm,
        points     = session.points,
    }
end

function WIVBN.ExportSession(id)
    local export = WIVBN.BuildSessionExport(id)
    if not export then
        return nil, "session not found"
    end

    local ok, json = pcall(WIVBN.json.encode, export)
    if not ok or type(json) ~= "string" then
        return nil, "json encode failed: " .. tostring(json)
    end

    return WIVBN.base64.encode(json)
end
