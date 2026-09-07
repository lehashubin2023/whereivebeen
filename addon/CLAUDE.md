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

Имя аддона берётся из контекста загрузки (`local ADDON_NAME = ...` в `Core.lua`)
и лежит в `WIVBN.ADDON_NAME` — переименование папки не ломает ни `ADDON_LOADED`,
ни чтение версии из `.toc`. `InitDB` вдобавок вызывается на `PLAYER_LOGIN`,
поэтому БД инициализируется даже если `ADDON_LOADED` не совпал.

Совместимость со старыми флейворами держится на `WIVBN.SafeRegister` — `pcall` вокруг
`RegisterEvent`, потому что `RegisterEvent` на неизвестное событие бросает ошибку
(`BARBER_SHOP_OPEN`, `GUILDBANKFRAME_OPENED`, `PET_STABLE_SHOW` есть не везде).
Тот же приём для API: `C_Spell.GetSpellInfo` → `GetSpellInfo`, `C_AddOns.GetAddOnMetadata`
→ `GetAddOnMetadata`, `IsFishingLoot`/`GetLootSourceInfo`/`GetPlayerInfoByGUID`/
`UnitCreatureType`/`C_QuestLog` через `and`-гарды.

## Модель данных SavedVariables

`WhereIveBeenDB` — **account-wide**, но активная сессия хранится **на персонажа**.

```
WhereIveBeenDB = {
  schema          = 2,        -- версия БД аддона (WIVBN.SCHEMA)
  lastSessionId   = <int>,    -- монотонный счётчик, защищает от переиспользования id
  activeSessions  = { ["Имя-Realm"] = <id>, ... },
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
координаты UI-карты (0..1), округлённые до `COORD_PRECISION` (5 знаков — ровно то,
что переживает хранение в `unsignedSmallInteger` на бэке). `t` — секунды от старта
сессии с точностью 0.1. `mapId` — UiMapID, может отсутствовать. Точка без `event` —
просто узел маршрута.

**Id сессии нельзя печатать через `%d`.** `time() * 1000` ≈ 1.79e12, а
`string.format("%d", …)` в клиенте WoW принимает только 32-битное целое и падает с
`integer overflow attempting to store …` — в отличие от ванильного Lua 5.1, где такой
код проходит, поэтому стенд эту ошибку не ловит. Для любого id используй
`WIVBN.FormatId(id)` (`%.0f`) и `%s`. На сериализацию это не влияет: `tostring`
даёт полное число (13 цифр укладываются в `%.14g`), так что dkjson пишет id верно.

Старое поле `activeSessionId` мигрируется в `activeSessions` идемпотентной
`MigrateActiveSession` при каждом `InitDB`. Поддерживается только схема точек 2;
поля схемы 1 (`itemId`/`itemName`/`count`/`member`/`place`) удалены и из аддона,
и из импортёра.

## Жизненный цикл сессии

- `PLAYER_LOGIN` → `InitDB()` + `ResetTracking()` + `ResumeOrIdle()`.
- `ResumeOrIdle`: чистит пустые сессии, предупреждает о размере хранилища; резюмит
  `activeSessions[текущий персонаж]`, если такая сессия есть **и принадлежит этому
  персонажу** (`BelongsToCurrentCharacter`); иначе **всегда стартует новую**.
  Состояния «нет сессии» после входа не существует.
- `StartSession(continuesFrom)`: отказывает, если сессия уже активна; чистит пустые,
  берёт `NextSessionId()` = `max(time()*1000, lastSessionId+1)` с поиском свободного
  слота (+0..999), снимает снапшот персонажа, включает подписку на боевой лог,
  печатает `Recording started — session N` и пишет базовый снапшот.
- `ResumeSession(id)`: считает офлайн-разрыв как `(time() - started) - t последней точки`,
  переставляет `tBase = time() - started` и `clock = GetTime()`, снимает `ended`,
  печатает `Recording resumed — session N, M points`. Если разрыв ≥ `RESUME_GAP_MIN`
  (60 с) — пишет `gap` с `reason = "login"`. Затем базовый снапшот.
- `SaveSessionBaseline()` (после старта, резюма и `clear`): синхронизирует
  `wasMounted`/`wasOnTaxi`/`wasDead`/`groupRoster` с реальностью, сбрасывает дедуп зоны
  и пишет стартовые события для активных состояний (`taxi` **или** `mount`, плюс
  `combat`, если игрок уже в бою), затем зону — или, если зона недоступна, просто
  позицию. Без этого сессия начиналась вслепую и первое событие такси могло оказаться
  «приземлением» без парного взлёта.
- `EndSession`: сливает буфер агрегации, ставит `ended`, гасит активную сессию
  и отписывается от боевого лога.
- `DeleteSession` / `ClearAllSessions`: если удалена активная сессия, **сразу стартует
  новая** — запись никогда не останавливается молча. `ForgetActiveSession` снимает id
  из ключей всех персонажей.
- `RotateSession`: `EndSession` + `StartSession(previous)` под флагом `rotating`.
  Триггерится только при `MAX_POINTS_SESSION > 0`.
- `PLAYER_LOGOUT` → `FlushPending()`; активная сессия сохраняется и продолжится
  со следующего входа.

**Жёстких лимитов хранилища нет** (`MAX_POINTS_SESSION`/`MAX_POINTS_TOTAL`/`MAX_SESSIONS`
= 0, коммит `c8a210c`), поэтому `EnforceBudget` и авторотация не срабатывают. Вместо
них `WarnStorage()` один раз за сеанс игры предупреждает, когда суммарно накоплено
больше `WARN_POINTS_TOTAL` (40000) точек; проверка идёт на входе, в `status`
и каждые `WARN_EVERY_POINTS` (500) записанных точек. Ничего не удаляется.

## Время

`SessionTime(session) = floor(((tBase + (GetTime() - clock)) * 10)) / 10`.

`GetTime()` — аптайм клиента, обнуляется при рестарте игры, поэтому `clock` в БД
осмыслен только после `ResumeSession`, который его переставляет на каждом входе.
`tBase` при резюме приравнивается к реальному прошедшему времени, то есть офлайн
входит в `t` — разрывы явно помечаются событием `gap`.

На бэке `t` конвертируется в децисекунды: `time = round(t * 10)` в `CreateWayPointDTO`.

## Три канала записи точек

1. **Периодический** — `OnUpdate` копит `elapsed`; каждые `WRITING_INTERVAL` (15 с)
   `SaveTimedPosition()` пишет точку, если сместились дальше `MIN_MOVE` (0.005
   в нормализованных координатах) или сменилась карта. Иначе точка не пишется.
2. **Событийный** — `SaveEvent(extra)`: сливает буфер агрегации, берёт позицию,
   пишет точку, сбрасывает таймер периодической записи и ставит точку на доводку.
   То есть интервал реально означает «15 с с последней любой записи».
3. **Агрегированный** — `PushAggregated(kind, entry)` копит однотипные события
   в `WIVBN.pending` и выливает их одной точкой (см. ниже).

`WritePoint` — единственная точка записи: округляет координаты, собирает
`{x, y, mapId, t}`, наливает сверху `extra` (поэтому `extra.t` может переопределить
время — так делает агрегатор), пушит в `session.points`, обновляет
`lastMapId/lastX/lastY` только при реальном `mapId`.

`GetPlayerPosition` возвращает nil, если нет `UiMapID`, нет позиции, координаты
ровно `0,0` (типично для инстансов на классик-флейворах) — **или карта слишком общая**.
Последнее проверяет `IsRoutableMap`: `C_Map.GetMapInfo(mapId).mapType` должен быть
не меньше `MIN_MAP_TYPE` (3 = `Enum.UIMapType.Zone`), то есть Zone/Dungeon/Micro
принимаются, а Cosmic/World/Continent — нет. Сразу после `PLAYER_LOGIN`
`GetBestMapForUnit` какое-то время отдаёт **материк** (1415 «Eastern Kingdoms» вместо
1453 «Stormwind City»), и такие точки и выпадали из отрисовки (у континентов нет
картинки), и рвали маршрут города на куски, потому что `BuildSessionZones`
ставит `gap` на каждой смене `map_id`. Теперь точка пишется без `mapId`,
а `RefinePoint` (до `REFINE_ATTEMPTS` = 12 попыток, 6 секунд) дожидается зоны.

**Устаревшая позиция никогда не подставляется.** Если координат нет, точка пишется
без `mapId`, а `RefinePoint(point)` до `REFINE_ATTEMPTS` (6) раз с шагом
`REFINE_DELAY` (0.5 с) пытается их дописать. Раньше событие после загрузочного экрана
получало координаты предыдущей локации и уже не исправлялось. `RefinePoint` вызывается
изнутри `SaveEvent` и `FlushPending`, поэтому обёртки на местах вызова не нужны.

## Каталог событий

Строка в `point.event` — контракт с бэкендом
([EventTypeEnum::fromSlug](../web/app/Enums/GameSession/EventTypeEnum.php)).
Все 13 слагов покрыты enum'ом.

| event | триггер | поля |
|---|---|---|
| `mount` | `UNIT_AURA` (player) → сравнение `IsMounted()` | `mounted` |
| `combat` | `PLAYER_REGEN_DISABLED` / `_ENABLED` | `inCombat` |
| `taxi` | опрос `UnitOnTaxi` каждые `STATE_INTERVAL` (0.5 с) | `onTaxi` |
| `death` | `PLAYER_DEAD` | `killer{name,npcId,spell,amount,pvp,class,creatureType}` или `environment` |
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

`mount` и `taxi` пишутся только при активной сессии — иначе флаг состояния
не обновляется и переход не теряется.

## Агрегация (Aggregate.lua)

Четыре агрегатора: `loot`, `group` и `visit` с окном `AGGREGATE_WINDOW` = 1.0 с,
`gather` — `GATHER_WINDOW` = 3.0 с.

- В `WIVBN.pending` живёт **ровно один** буфер: `{kind, anchor, data, at}`. Приход
  события другого типа принудительно выливает предыдущий.
- `anchor` фиксирует позицию и `t` **первого** события пачки — вылитая точка получает
  время начала пачки, а не конца.
- `Arm(window)` перевзводит таймер на каждом добавлении (скользящее окно) и защищён
  счётчиком `generation`: сработает только последний взведённый таймер. Скольжение
  ограничено сверху `AGGREGATE_MAX` (10 с) — непрерывный поток лута на месте больше
  не копится бесконечно.
- Досрочный слив: `FlushPending` при смене типа, `SaveEvent`, `EndSession`,
  `ClearSession`, `DeleteSession`, `PLAYER_LOGOUT`; `FlushKind("loot")` и проверка
  gather в `OnLootClosed`; `CheckPendingPosition` каждые 0.5 с выливает буфер,
  если игрок сменил карту или отошёл дальше `MIN_MOVE`.
- `ReclassifyPending(from, to, transform)` переводит свежий (моложе `ADOPT_WINDOW`,
  1 с) буфер в другой тип и перевзводит таймер под его окно. Нужна для случая,
  когда `CHAT_MSG_LOOT` опережает `LOOT_OPENED` и сбор успел попасть в `loot`.
- Дедупликация внутри пачки: у `loot`/`gather` предметы схлопываются по `id`
  с суммированием `n`; у `visit` места уникализируются через `seen`.

## Трекинг (Tracking.lua)

- **Убийца.** `COMBAT_LOG_EVENT_UNFILTERED` подписан только пока сессия пишется
  (`SetCombatLogEnabled`). Фильтруется по `destGuid == playerGuid` и словарю
  `DAMAGE_EVENTS`; последний удар кладётся в `WIVBN.lastHit` вместе с `guid`.
  `KillerPayload()` отдаёт его, если он свежее `KILLER_WINDOW` (10 с).
  `ENVIRONMENTAL_DAMAGE` разбирается отдельно (`select(12)` → тип среды, урон),
  `SWING_DAMAGE` берёт урон из arg12, остальные — spellName/amount из arg13/arg15.
- **Класс убийцы** резолвится лениво, только в момент смерти (`KillerClass`), чтобы
  не нагружать боевой лог: для игрока — `GetPlayerInfoByGUID` даёт англоязычный класс
  (`MAGE`), для NPC — `UnitCreatureType` по юнитам `target`/`mouseover`/`focus`/`boss1..5`,
  если убийца среди них. Для NPC вне этих слотов тип не определяется — поле просто
  отсутствует.
- **Каст.** `UNIT_SPELLCAST_SUCCEEDED` (player) пишет `lastCast{id,name,at}`.
  Используется дважды: как заклинание сбора (в пределах `GATHER_WINDOW`)
  и как причина разрыва маршрута (в пределах `TELEPORT_WINDOW` = 20 с).
- **Причина разрыва.** `GapReason()`: мертвы → `death`, на такси → `taxi`,
  свежий каст → `spell` + id/имя, иначе `loading`.
- **Сбор.** `LOOT_OPENED` считает узлом либо рыбалку (`IsFishingLoot`), либо источник
  лута с GUID вида `GameObject-…`. Никакой таблицы spellID нет — профессия достаётся
  только для рыбалки, остальное доопределяет веб по `spellId`/`objectId`.
  `IsGatherFresh()` пускает предмет в `gather`, если узел моложе `GATHER_WINDOW`
  и с закрытия окна лута прошло не больше `GATHER_TAIL` (0.5 с) — хвост для
  догоняющих `CHAT_MSG_LOOT`, но не открытая дверь для лута со следующего моба.
- **Зона.** `SaveZoneState` дедуплицирует по одному `GetZoneText()` и возвращает
  записанную точку (или nil). Карта в ключ дедупа не входит намеренно: она может
  смениться с материка на зону при неизменном названии, и тогда писалось бы
  второе событие о той же зоне.
- `GuidId(guid)` достаёт NPC/объект id — шестой сегмент GUID для
  `Creature`/`Vehicle`/`GameObject`, иначе nil. `NpcInfo()` читает юнит `npc`.

## Разбор сообщений о луте

`BuildPattern` собирает Lua-паттерн из локализованного `LOOT_ITEM_SELF` и родственных
глобалов. Поддерживаются и обычные `%s`/`%d`, и позиционные `%1$s`/`%2$d`: функция
возвращает паттерн и `order` — соответствие «номер захвата → логический аргумент»,
поэтому локали с переставленными аргументами (сначала количество, потом предмет)
парсятся верно. Формат без спецификаторов отбрасывается (`pattern = nil`).

## Экспорт (Export.lua)

`BuildSessionExport(id)` отклоняет отсутствующую сессию, сессию без точек и сессию
без `char`/`realm` (такую бэкенд не примет). Собирает конверт:

```
schema, sessionId, continuesFrom, started, ended, char, realm, faction, class, level,
locale, addon (версия из .toc), gameVersion, build, version (tocVersion), points
```

`EncodeExport`: dkjson → `LibDeflate:CompressDeflate(level 9)` → base64 →
префикс `WIVB1:`. Третьим значением возвращается `note` — почему экспорт оказался
несжатым; `ShowExportWindow` печатает его в чат и в подпись окна, так что тихая
деградация в сырой JSON больше не выглядит нормой.

`WIVBN.Deflate()` резолвит библиотеку лениво: сначала `WIVBN.deflate`, затем
`LibStub:GetLibrary("LibDeflate", true)`. Это обязательно — `Libs/LibDeflate.lua`
при наличии LibStub с уже зарегистрированной версией ≥ 1.0.2 делает `return lib`
на строке 112, и присвоение `WhereIveBeen.deflate` в конце файла не выполняется.
Без резолва экспорт молча уходил сырым JSON у любого, у кого стоит WeakAuras,
Details или ElvUI.

Бэкенд принимает оба варианта
([DecodeRawInput](../web/app/Actions/GameSession/DecodeRawInput.php): `WIVB1:` →
`base64_decode` + `gzinflate`, глубина `json_decode` = 6).

`ExportSession` штампует `exportedAt = time()` — метка для маркера `exported` в UI.

Измеренное сжатие — ~4.6× (406 точек: 17809 → 3882 байта).

## UI и команды

`/whereivebeen`, `/wivebeen`, `/wivbn`, `/wivb`: `start`, `end`, `status`, `clear`,
`clear_all`, `delete <id>`, `prune <points>`, `log` (последние 20 точек в
человекочитаемом виде через `FormatPoint`/`DescribePoint`), `get_sessions`,
`raw` (тумблер сырого JSON, не персистится), `help`.

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
- `sessionId` → `unsignedBigInteger`. Повторный импорт того же `game_session_id`
  **замещает** сохранённую сессию: `CreateGameSession::replace` обновляет поля,
  удаляет её точки и события и пишет маршрут заново, сохраняя id записи в БД.
- Максимальный размер строки импорта — 20 МБ (`GameSession::MAX_IMPORT_SIZE`).

## Проверка изменений

Синтаксис всех файлов под Lua 5.1 (интерпретатора в WSL нет, поэтому через docker):

```bash
MSYS_NO_PATHCONV=1 wsl -d Ubuntu -- docker run --rm \
  -v /home/aleksey/home/whereivebeen/addon:/addon:ro alpine:3.20 \
  sh -c "apk add --no-cache lua5.1 >/dev/null 2>&1; \
         find /addon -maxdepth 1 -name '*.lua' -exec luac5.1 -p {} +"
```

Бэкенд — `make test` из `web/` (114+ тестов), `pint --test`, `phpstan analyse`
(на level 7 в репозитории исторически 41 ошибка, это baseline — сверяйся с ним,
а не с нулём).

## Что осталось незакрытым

1. **`subZone` фиксируется один раз на зону.** Дедуп `SaveZoneState` идёт по
   одному названию зоны без `subZone`, поэтому переходы между подзонами не пишутся,
   а записанный `subZone` — тот, что был при входе в зону. Решено не трогать.
2. **Разрешение маршрута грубое**: минимум 15 с между точками, порог движения
   `MIN_MOVE` задан в долях карты, поэтому в ярдах он разный на большой и маленькой
   карте. Адаптивный семплинг по дистанции сознательно отложен. Это главный
   оставшийся источник «рваной» линии: на замере реальной сессии (тогда ещё
   с интервалом 20 с) перелёт через пять зон уложился в 20 точек, и на
   Elwynn Forest пришлось 3 точки — а сегмент из одной точки `RouteMap.vue`
   не рисует вовсе (`current.coords.length > 1`). Интервал снижен до 15 с,
   что даёт примерно в полтора раза более плотный трек, но не убирает причину.
3. **Континенты не имеют картинки в `maps`**, и `BuildSessionZones` пропускает
   такие зоны целиком (`continue`). После фильтра по `mapType` точки туда больше
   не попадают, но уже импортированные сессии с `map_id` материка так и останутся
   без отрисовки — и в SavedVariables эти точки тоже не переписываются задним числом.

   Дребезг границы зон (карта на секунды прыгает в соседнюю и обратно) — не разрыв:
   `BuildSessionZones::defineGap` считает паузу с предыдущей точки **той же карты**
   и рвёт линию только после `GAP_SECONDS` (60 с) либо на явном событии `gap`.
4. **События внутри инстансов.** Там, где `GetPlayerMapPosition` даёт `0,0`
   (классик-подземелья), маршрут не пишется, а события остаются без координат —
   лучше, чем ложные, но карта подземелья по-прежнему не строится.
5. **Экспорт очень большой сессии** идёт одним синхронным `CompressDeflate`
   на чистом Lua и подвешивает клиент; `WarnStorage` только предупреждает.
