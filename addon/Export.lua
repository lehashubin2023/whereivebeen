local WIVBN = WhereIveBeen

WIVBN.EXPORT_PREFIX = "WIVB1:"

local function AddonVersion()
    local get = (C_AddOns and C_AddOns.GetAddOnMetadata) or GetAddOnMetadata
    if not get then return nil end

    local ok, version = pcall(get, "WhereIveBeen", "Version")

    return ok and version or nil
end

function WIVBN.BuildSessionExport(id)
    local session = WhereIveBeenDB and WhereIveBeenDB.sessions and WhereIveBeenDB.sessions[id]
    if not session then
        return nil, "session not found"
    end

    if WIVBN.PointCount(session) == 0 then
        return nil, "session has no points"
    end

    if not session.char or not session.realm then
        return nil, "session has no character name and cannot be imported, delete it"
    end

    local gameVersion, build, _, tocVersion = GetBuildInfo()

    return {
        schema        = session.schema or 1,
        sessionId     = id,
        continuesFrom = session.continuesFrom,
        started       = session.started,
        ended         = session.ended,
        char          = session.char,
        realm         = session.realm,
        faction       = session.faction,
        class         = session.class,
        level         = session.level,
        locale        = GetLocale(),
        addon         = AddonVersion(),
        gameVersion   = gameVersion,
        build         = build,
        version       = tocVersion,
        points        = session.points,
    }
end

function WIVBN.Deflate()
    if WIVBN.deflate then return WIVBN.deflate end

    if LibStub then
        local ok, lib = pcall(LibStub.GetLibrary, LibStub, "LibDeflate", true)
        if ok and type(lib) == "table" and lib.CompressDeflate then
            WIVBN.deflate = lib
        end
    end

    return WIVBN.deflate
end

function WIVBN.EncodeExport(export)
    local ok, json = pcall(WIVBN.json.encode, export)
    if not ok or type(json) ~= "string" then
        return nil, "json encode failed: " .. tostring(json)
    end

    if WIVBN.rawExport then
        return json, nil, "raw JSON"
    end

    local deflate = WIVBN.Deflate()
    if not deflate then
        return json, nil, "uncompressed: LibDeflate not available"
    end

    if not WIVBN.base64 then
        return json, nil, "uncompressed: base64 not available"
    end

    local compressed
    ok, compressed = pcall(deflate.CompressDeflate, deflate, json, { level = 9 })
    if not ok or type(compressed) ~= "string" then
        return json, nil, "uncompressed: deflate failed"
    end

    local encoded
    ok, encoded = pcall(WIVBN.base64.encode, compressed)
    if not ok or type(encoded) ~= "string" then
        return json, nil, "uncompressed: base64 failed"
    end

    return WIVBN.EXPORT_PREFIX .. encoded
end

function WIVBN.ExportSession(id)
    local export, buildError = WIVBN.BuildSessionExport(id)
    if not export then
        return nil, buildError
    end

    local data, encodeError, note = WIVBN.EncodeExport(export)
    if not data then
        return nil, encodeError
    end

    WhereIveBeenDB.sessions[id].exportedAt = time()

    return data, nil, note
end
