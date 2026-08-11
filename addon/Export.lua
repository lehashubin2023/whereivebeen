local WIVBN = WhereIveBeen

-- Собирает сессию в объект для экспорта.
-- Отдаём «сырьё», как лежит в логах: координаты 0..1 (float), t в секундах,
-- событийные поля как есть. Масштабирование координат и протаскивание state —
-- задача воркера на стороне веба (см. web/plan.md §5).
function WIVBN.BuildSessionExport(id)
    local session = WhereIveBeenDB and WhereIveBeenDB.sessions and WhereIveBeenDB.sessions[id]
    if not session then return nil end

    return {
        version    = select(4, GetBuildInfo()),   -- числовой tocversion клиента (напр. 20506) = версия ВоВ
        sessionID  = id,                           -- ключ массива сессий = game_session_id в веб-схеме
        started    = session.started,
        char       = session.char,
        realm      = session.realm,
        points     = session.points,               -- пустой → dkjson отдаёт [], непустой → массив объектов
    }
    -- clock (GetTime, аптайм-относительный) намеренно не экспортируем — снаружи бесполезен.
end

-- Сессия → JSON (dkjson) → base64 (lbase64).
-- Возвращает base64-строку, либо nil + текст ошибки.
function WIVBN.ExportSession(id)
    local export = WIVBN.BuildSessionExport(id)
    if not export then
        return nil, "session not found"
    end

    -- dkjson при ошибке бросает error(), а не возвращает nil — ловим через pcall.
    local ok, json = pcall(WIVBN.json.encode, export)
    if not ok or type(json) ~= "string" then
        return nil, "json encode failed: " .. tostring(json)
    end

    -- JSON и base64 работают на уровне байт → UTF-8 (кириллица в квестах/предметах)
    -- и цветокоды |cff..|r переживают round-trip без искажений.
    return WIVBN.base64.encode(json)
end
