# CLAUDE.md — аддон WhereIveBeen (Lua, WoW)

Разбор бизнес-логики аддона. Опорные документы: [../CLAUDE.md](../CLAUDE.md),
[../web/plan.md](../web/plan.md), [../plan-refactor.md](../plan-refactor.md).

## Роль в системе

Аддон — единственный источник данных всего продукта. Он пишет трек персонажа и события
в SavedVariables, схлопывает шум, сериализует сессию в компактную строку и отдаёт её
пользователю через `EditBox` для копипаста в веб. Обратного канала нет: аддон никогда
не ходит в сеть и ничего не читает от бэкенда.

## Загрузка, флейворы, порядок файлов

Семь `.toc` с одинаковым списком файлов, различаются только `## Interface`:
Vanilla 11507, TBC 20506, Wrath 30403, Cata 40402, Mists 50501, Mainline/дефолт 110207.

Порядок загрузки значим — библиотеки первыми вешают себя в общий неймспейс:
`Libs/dkjson` → `WhereIveBeen.json`, `Libs/base64` → `WhereIveBeen.base64`,
`Libs/LibDeflate` → `WhereIveBeen.deflate`. Далее `Core` (константы, обработчики,
регистрация), `Session`, `Methods`, `Aggregate`, `Tracking`, `Export`, `UI`, `Commands`.

Общий алиас во всех файлах: `local WIVBN = WhereIveBeen`.

Совместимость со старыми флейворами держится на `WIVBN.SafeRegister` — `pcall` вокруг
`RegisterEvent`, потому что `RegisterEvent` на неизвестное событие бросает ошибку
(`BARBER_SHOP_OPEN`, `GUILDBANKFRAME_OPENED`, `PET_STABLE_SHOW` есть не везде).
Тот же приём для API: `C_Spell.GetSpellInfo` → `GetSpellInfo`, `C_AddOns.GetAddOnMetadata`
→ `GetAddOnMetadata`, `IsFishingLoot`/`GetLootSourceInfo`/`C_QuestLog` через `and`-гарды.

## Модель данных SavedVariables

`WhereIveBeenDB` — **account-wide**, не per-character.

```
WhereIveBeenDB = {
  schema          = 2,        -- версия БД аддона (WIVBN.SCHEMA)
  initialized     = true,     -- первая сессия уже создавалась автоматически
  lastSessionId   = <int>,    -- монотонный счётчик, защищает от переиспользования id
  activeSessionId = <int|nil>,
  sessions = {
    [id] = {
      id, schema, started (unix), ended (unix|nil), exportedAt (unix|nil),
      clock (GetTime на момент старта/резюма), tBase (накопленные секунды),
      char, realm, faction, class, level, continuesFrom (id|nil),
      points = { <point>, ... }   -- плотный массив, порядок = порядок записи
    }
  }
}
```

Точка: `{ x, y, mapId, t }` плюс произвольные поля события. `x`/`y` — нормализованные
координаты UI-карты (0..1), `t` — секунды от старта сессии с точностью 0.1,
`mapId` — UiMapID. Точка без `event` — просто узел маршрута.

`WIVBN.SCHEMA = 2`. `InitDB` при расхождении версий добивает старым сессиям
`id`/`points`/`tBase`/`schema = 1` и поднимает `WhereIveBeenDB.schema`. Конвертации
формата точек нет — версия едет в экспорте пер-сессионно, разбирается на бэке.

## Жизненный цикл сессии

- `PLAYER_LOGIN` → `ResetTracking()` + `ResumeOrIdle()`.
- `ResumeOrIdle`: чистит пустые сессии; если есть `activeSessionId` — `ResumeSession`;
  иначе, если `initialized` ещё не выставлен, — автоматически `StartSession`
  (единственный раз за всю жизнь БД); иначе просто сообщает, что сессии нет.
- `StartSession(continuesFrom)`: отказывает, если сессия уже активна; чистит пустые,
  прогоняет бюджет, берёт `NextSessionId()` = `max(time()*1000, lastSessionId+1)`
  с поиском свободного слота (+0..999), снимает снапшот персонажа, обнуляет
  `lastMapId/lastX/lastY`.
- `ResumeSession(id)`: считает офлайн-разрыв как `(time() - started) - t последней точки`,
  переставляет `tBase = time() - started` и `clock = GetTime()`, снимает `ended`.
  Если разрыв ≥ `RESUME_GAP_MIN` (60 с) — пишет событие `gap` с `reason = "login"`.
- `EndSession`: сливает буфер агрегации, ставит `ended`, гасит `sessionId`
  и `activeSessionId`.
- `RotateSession`: `EndSession` + `StartSession(previous)` под флагом `rotating`
  (защита от рекурсии из `WritePoint`). Триггерится только при `MAX_POINTS_SESSION > 0`.
- `ClearSession` (сброс точек, сессия остаётся активной), `DeleteSession(id)`,
  `ClearAllSessions`, `PruneSessions(minPoints)`.
- `PLAYER_LOGOUT` → `FlushPending()`; `sessionId`/`activeSessionId` сохраняются,
  поэтому запись продолжается со следующего входа.

**Бюджет хранилища выключен**: `MAX_POINTS_SESSION`/`MAX_POINTS_TOTAL`/`MAX_SESSIONS`
= 0 (коммит `c8a210c`), при нуле `EnforceBudget` и авторотация — мёртвый код.
Плановые значения были 10000/40000/50.

## Время

`SessionTime(session) = floor(((tBase + (GetTime() - clock)) * 10)) / 10`.

`GetTime()` — аптайм клиента, обнуляется при рестарте игры, поэтому `clock` в БД
осмыслен только после `ResumeSession`, который его переставляет на каждом входе.
`tBase` при резюме приравнивается к реальному прошедшему времени, то есть офлайн
входит в `t` — разрывы явно помечаются событием `gap`.

На бэке `t` конвертируется в децисекунды: `time = round(t * 10)` в `CreateWayPointDTO`.

## Три канала записи точек

1. **Периодический** — `OnUpdate` копит `elapsed`; каждые `WRITING_INTERVAL` (20 с)
   `SaveTimedPosition()` пишет точку, если сместились дальше `MIN_MOVE` (0.005
   в нормализованных координатах) или сменилась карта. Иначе точка не пишется.
2. **Событийный** — `SaveEvent(extra)`: сливает буфер агрегации, берёт позицию
   (с откатом на `lastMapId/lastX/lastY`), пишет точку, сбрасывает 20-секундный таймер.
   То есть интервал реально означает «20 с с последней любой записи».
3. **Агрегированный** — `PushAggregated(kind, entry)` копит однотипные события
   в `WIVBN.pending` и выливает их одной точкой (см. ниже).

`WritePoint` — единственная точка записи: собирает `{x, y, mapId, t}`, наливает сверху
`extra` (поэтому `extra.t` может переопределить время — так делает агрегатор),
пушит в `session.points`, обновляет `lastMapId/lastX/lastY`, при переполнении зовёт
`RotateSession`.

`GetPlayerPosition` возвращает nil, если нет `UiMapID`, нет позиции или координаты
ровно `0,0` (типично для инстансов на классик-флейворах).

`RefinePoint(point)` — доводка точки без координат: через `REFINE_DELAY` (0.5 с)
один раз пытается подставить настоящую позицию. Работает **только** если `point.mapId`
пустой.

## Каталог событий

Строка в `point.event` — контракт с бэкендом
([EventTypeEnum::fromSlug](../web/app/Enums/GameSession/EventTypeEnum.php)).
Все 13 слагов покрыты enum'ом.

| event | триггер | поля |
|---|---|---|
| `mount` | `UNIT_AURA` (player) → сравнение `IsMounted()` | `mounted` |
| `combat` | `PLAYER_REGEN_DISABLED` / `_ENABLED` | `inCombat` |
| `taxi` | опрос `UnitOnTaxi` каждые `STATE_INTERVAL` (0.5 с) | `onTaxi` |
| `death` | `PLAYER_DEAD` | `killer{name,npcId,spell,amount,pvp}` или `environment` |
| `resurrect` | `PLAYER_UNGHOST`, либо `PLAYER_ALIVE` при `not UnitIsGhost` | — |
| `levelup` | `PLAYER_LEVEL_UP` | `level` |
| `quest` | `QUEST_ACCEPTED` / `QUEST_TURNED_IN` | `action` (accept/turnin), `questId`, `title` |
| `zone` | `ZONE_CHANGED` / `_NEW_AREA` / `_INDOORS`, `PLAYER_ENTERING_WORLD` | `zone`, `subZone` |
| `gap` | `PLAYER_ENTERING_WORLD` не при логине/релоаде; резюм сессии | `reason`, `spellId`, `spellName`, `seconds` |
| `loot` | `CHAT_MSG_LOOT`, агрегируется | `items[]{id,name,n}` |
| `gather` | тот же лут, но опознан узел, агрегируется | `node{name,objectId,prof,spellId,spellName}`, `items[]` |
| `visit` | `MERCHANT_SHOW`, `BANKFRAME_OPENED`, `AUCTION_HOUSE_SHOW`, `MAIL_SHOW`, `TRAINER_SHOW`, `TAXIMAP_OPENED`, `GUILDBANKFRAME_OPENED`, `PET_STABLE_SHOW`, `BARBER_SHOP_OPEN`, `TRADE_SHOW`; агрегируется | `places[]`, `npcId`, `npcName` |
| `group` | `GROUP_ROSTER_UPDATE` → диф ростера, агрегируется | `joined[]`, `left[]` |

`visit` для торговца дополнительно даёт место `repair`, если `CanMerchantRepair()`.

## Агрегация (Aggregate.lua)

Четыре агрегатора: `loot`, `group` и `visit` с окном `AGGREGATE_WINDOW` = 1.0 с,
`gather` — `GATHER_WINDOW` = 3.0 с.

- В `WIVBN.pending` живёт **ровно один** буфер: `{kind, anchor, data}`. Приход события
  другого типа принудительно выливает предыдущий.
- `anchor` фиксирует позицию и `t` **первого** события пачки — вылитая точка получает
  время начала пачки, а не конца.
- `Arm(window)` перевзводит таймер на каждом добавлении (скользящее окно) и защищён
  счётчиком `generation`: сработает только последний взведённый таймер.
- Досрочный слив: `FlushPending` при смене типа, `SaveEvent`, `EndSession`,
  `ClearSession`, `DeleteSession`, `PLAYER_LOGOUT`; `FlushKind("loot")` и проверка
  gather в `OnLootClosed`; `CheckPendingPosition` каждые 0.5 с выливает буфер,
  если игрок сменил карту или отошёл дальше `MIN_MOVE`.
- Дедупликация внутри пачки: у `loot`/`gather` предметы схлопываются по `id`
  с суммированием `n`; у `visit` места уникализируются через `seen`.

## Трекинг (Tracking.lua)

- **Убийца.** `COMBAT_LOG_EVENT_UNFILTERED` фильтруется по `destGuid == playerGuid`
  и словарю `DAMAGE_EVENTS`. Последний удар кладётся в `WIVBN.lastHit`.
  `KillerPayload()` отдаёт его только если он свежее `KILLER_WINDOW` (10 с).
  `ENVIRONMENTAL_DAMAGE` разбирается отдельно (`select(12)` → тип среды, урон),
  `SWING_DAMAGE` берёт урон из arg12, остальные — spellName/amount из arg13/arg15.
- **Каст.** `UNIT_SPELLCAST_SUCCEEDED` (player) пишет `lastCast{id,name,at}`.
  Используется дважды: как заклинание сбора (в пределах `GATHER_WINDOW`)
  и как причина разрыва маршрута (в пределах `TELEPORT_WINDOW` = 20 с).
- **Причина разрыва.** `GapReason()`: мертвы → `death`, на такси → `taxi`,
  свежий каст → `spell` + id/имя, иначе `loading`.
- **Сбор.** `LOOT_OPENED` считает узлом либо рыбалку (`IsFishingLoot`), либо источник
  лута с GUID вида `GameObject-…`. Никакой таблицы spellID нет — профессия достаётся
  только для рыбалки, остальное доопределяет веб по `spellId`/`objectId`.
  `PushLoot` маршрутизирует предмет в `gather`, если узел свежее `GATHER_WINDOW`,
  иначе в `loot`.
- **Зона.** `SaveZoneState` дедуплицирует по паре `(GetZoneText(), UiMapID)`.
- `GuidId(guid)` достаёт NPC/объект id — шестой сегмент GUID для
  `Creature`/`Vehicle`/`GameObject`, иначе nil. `NpcInfo()` читает юнит `npc`.

## Экспорт (Export.lua)

`BuildSessionExport(id)` отклоняет отсутствующую сессию, сессию без точек и сессию
без `char`/`realm` (такую бэкенд не примет). Собирает конверт:

```
schema, sessionId, continuesFrom, started, ended, char, realm, faction, class, level,
locale, addon (версия из .toc), gameVersion, build, version (tocVersion), points
```

`EncodeExport`: dkjson → `LibDeflate:CompressDeflate(level 9)` → base64 →
префикс `WIVB1:`. На любом сбое (нет библиотек, флаг `rawExport`, ошибка сжатия
или кодирования) молча деградирует до сырого JSON — бэкенд принимает оба варианта
([DecodeRawInput](../web/app/Actions/GameSession/DecodeRawInput.php): `WIVB1:` →
`base64_decode` + `gzinflate`, глубина `json_decode` = 6).

`ExportSession` штампует `exportedAt = time()` — метка для вытеснения по бюджету
(сейчас не используется) и для маркера `exported` в UI.

Измеренное сжатие — ~4.6× (406 точек: 17809 → 3882 байта).

## UI и команды

`/whereivebeen`, `/wivebeen`, `/wrivbn`: `start`, `end`, `status`, `clear`, `clear_all`,
`delete <id>`, `prune <points>`, `log` (последние 20 точек в человекочитаемом виде
через `FormatPoint`/`DescribePoint`), `get_sessions`, `raw` (тумблер сырого JSON,
не персистится), `help`.

`get_sessions` открывает окно со списком сессий (только непустые, сортировка по id
по убыванию, `FauxScrollFrame`, 12 строк): ЛКМ — экспорт в окно с `EditBox`
(текст защищён от правки через `OnTextChanged`, автовыделение, Ctrl+C),
ПКМ — подтверждение удаления через `StaticPopup`.

## Контракт с бэкендом — что важно не сломать

- Строки `point.event` ↔ `EventTypeEnum::slug()`. Новое событие требует правки enum,
  `GameSessionJsonValidator`, `DescribeEvent`, `ShowSessionEvent` и `eventStyles.ts`.
- Любое новое вложенное поле точки повышает требуемую глубину `json_decode`
  (сейчас ровно 6: root → points → point → items → item).
- `x`/`y` уезжают в `unsignedSmallInteger` через мутатор `WayPoint` (×65535),
  `map_id` — `unsignedSmallInteger`, `t` — децисекунды в `unsignedInteger`.
- `sessionId` → `unsignedBigInteger`, уникальность импорта — `UNIQUE(user_id, game_session_id)`.
- Максимальный размер строки импорта — 20 МБ (`GameSession::MAX_IMPORT_SIZE`).

## Известные проблемы и подводные камни

1. **Сессии не привязаны к персонажу.** `WhereIveBeenDB` account-wide, `ResumeOrIdle`
   резюмит `activeSessionId` без сверки с `UnitName("player")`. Вход другим персонажем
   дописывает его маршрут в чужую сессию, помеченную `char` первого.
2. **`RefinePoint` не работает там, где он нужен.** `SaveEvent` и `Anchor()` при
   отсутствии позиции откатываются на `lastMapId/lastX/lastY`, после чего `point.mapId`
   не пуст и `RefinePoint` выходит на первой строке. Итог: событие после загрузочного
   экрана (`gap`, `zone`) остаётся на координатах предыдущей локации.
3. **`subZone` фиксируется один раз на зону.** Дедуп `SaveZoneState` идёт по
   `(zone, mapId)` без `subZone`, поэтому переходы между подзонами не пишутся.
4. **У новой сессии нет базовой точки.** `StartSession` не пишет ни позицию, ни зону,
   ни состояния `mounted`/`combat`/`taxi`; `lastZone` (локальная в `Tracking.lua`)
   не сбрасывается, поэтому первое событие `zone` подавляется дедупом. Сессия начинается
   пустой до первого срабатывания 20-секундного таймера.
5. **Бюджет хранилища выключен** (`MAX_* = 0`), `WhereIveBeenDB` растёт неограниченно.
   На большой БД пострадают сериализация SavedVariables при выходе, чистый Lua-deflate
   при экспорте и `SetText` на многомегабайтной строке в `EditBox`.
6. **Скользящее окно агрегации без потолка.** `Arm` перевзводится на каждом событии,
   поэтому непрерывный поток лута на месте может копиться неограниченно долго
   (страхует только `CheckPendingPosition` при движении).
7. **Окно сбора протекает.** `OnLootClosed` не гасит `gatherNode`/`gatherAt`, лут
   с моба в течение 3 с после закрытия узла попадёт в `gather` с чужим узлом.
   Обратная ошибка возможна при мгновенном автолуте, если `CHAT_MSG_LOOT` опередит
   `LOOT_OPENED`.
8. **Парсер лута не понимает позиционные спецификаторы.** `BuildPattern` обрабатывает
   только `%s`/`%d`; в локалях с `%1$s`/`%2$d` шаблон не соберётся и лут молча
   перестанет писаться.
9. **События внутри инстансов.** Там, где `GetPlayerMapPosition` даёт `0,0`
   (классик-подземелья), маршрут не пишется, а события садятся на последнюю
   уличную координату.
10. **Разрешение маршрута грубое**: минимум 20 с между точками, порог движения
    `MIN_MOVE` задан в долях карты, поэтому в ярдах он разный на разных картах.
11. **Координаты не округляются** перед сериализацией — dkjson печатает `%.14g`,
    хотя БД хранит всего 1/65535 (~5 знаков). Экспорт заметно толще необходимого.
12. **`/wivbn` не существует.** `ResumeOrIdle` советует `/wivbn start`, а
    зарегистрированы `/whereivebeen`, `/wivebeen`, `/wrivbn` (последний похож на опечатку).
13. **Удаление активной сессии молча останавливает запись** — ни `DeleteSession`,
    ни `ClearAllSessions` об этом не сообщают.
14. **`ADDON_LOADED` сверяется со строкой `"WhereIveBeen"`.** Переименование папки
    ломает `InitDB`, и `PLAYER_LOGIN` падает на индексации `nil`.
15. **Повторный импорт той же сессии невозможен** — `UNIQUE(user_id, game_session_id)`.
    Долгую сессию нельзя выгрузить частями и дослать хвост.
16. `points.*.itemId`/`itemName`/`count`/`member`/`place` в валидаторе — наследие
    схемы 1, текущий аддон их не шлёт (в `DescribeItems`/`DescribeGroup` остались
    ветки обратной совместимости).
