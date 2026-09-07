# План правок: аддон + контракт с бэкендом

Рабочий план на цикл исправлений. Опорная архитектура — [web/plan.md](web/plan.md),
она не пересматривается.

## Принятые решения

| Вопрос | Решение |
|---|---|
| Кап SavedVariables | Авторотация: кап 10k точек на сессию → продолжение новой сессией с `continuesFrom`. Глобальный бюджет 40k точек с вытеснением старых **экспортированных** сессий |
| Транспорт экспорта | LibDeflate (raw deflate) + собственный base64 → маркер `WIVB1:`. Сырой JSON остаётся под флагом для отладки |
| Мировые координаты | **Не делаем.** Сшивка карт отложена, маршрут остаётся пер-картовым |
| Схлопывание событий | **Только в аддоне**, буфер агрегации с окном. Воркер импорта не трогаем |
| ID сессии | `time() * 1000 + counter`, колонка на бэке → `unsignedBigInteger` |

Явно **не** входит в этот заход: `state`-битфлаги на точке, ускорение семплинга,
убийства мобов, прогресс целей квестов, XP/деньги/репутация.

---

## Этап 0. Подготовка

**0.1** Завести `addon/Libs/LibDeflate.lua` (чистый Lua, работает на всех флейворах).

**0.2** Завести `addon/Libs/Base64.lua` — стандартный base64 (не `LibDeflate.EncodeForPrint`,
у него собственный алфавит, который пришлось бы декодировать вручную на PHP).
Нужен только `Encode`; декодирование живёт на бэке.

**0.3** Хелпер безопасной регистрации событий в `Core.lua`:

```lua
function WIVBN.SafeRegister(frame, event)
    return pcall(frame.RegisterEvent, frame, event)
end
```

Часть новых событий (`BARBER_SHOP_OPEN`, `GUILDBANKFRAME_OPENED`, `PET_STABLE_SHOW`)
отсутствует в старых флейворах, а `RegisterEvent` на неизвестное событие бросает ошибку.

**0.4** Новые файлы аддона: `Aggregate.lua` (буфер схлопывания), `Tracking.lua`
(боевой лог, детекция сбора, зоны, причина `gap`). Порядок загрузки в TOC:
`Libs → Core → Session → Methods → Aggregate → Tracking → Export → UI → Commands`.

---

## Этап 1. Жизненный цикл сессии

Закрывает: *сессия стартует на каждый ADDON_LOADED*, *коллизия ID*, *ClearSession*,
*ended*, *кап и авторотация*, *пустые сессии*.

**1.1 Структура БД аддона (`WhereIveBeenDB`)**

```lua
{
  schema          = 2,
  initialized     = true,
  activeSessionId = <id|nil>,
  sessions = {
    [id] = {
      id, started, ended, char, realm, faction, class, level,
      continuesFrom = <id|nil>,
      exportedAt    = <ts|nil>,
      clock         = <GetTime() на момент старта/резюма>,
      tBase         = <t на момент резюма>,
      points        = {},
    }
  }
}
```

**1.2 Разделить инициализацию** ([addon/Core.lua:22-27](addon/Core.lua#L22-L27))

- `ADDON_LOADED` → только `InitDB()` + миграция схемы. Стартовать сессию **нельзя**:
  `UnitName("player")` и `GetRealmName()` на этом этапе могут вернуть nil, и тогда
  `char`/`realm` выпадут из JSON, а валидатор бэкенда (`required|string`) завалит импорт.
- `PLAYER_LOGIN` → `WIVBN.ResumeOrIdle()`:
  - есть `activeSessionId` и сессия жива → **резюм**;
  - нет, но `initialized` не выставлен (первый запуск) → автостарт + приветствие;
  - нет и уже инициализировано → ничего, подсказка `/wivbn start`.

Так `/wivbn end` наконец переживает релог, а `/reload` перестаёт плодить пустые сессии.

**1.3 Непрерывное `t` через релог**

`GetTime()` сбрасывается при перезапуске клиента, поэтому базу времени пересчитываем
на каждом резюме:

```lua
session.tBase = time() - session.started
session.clock = GetTime()
-- далее: t = session.tBase + (GetTime() - session.clock)
```

Внутри одного запуска клиента точность 0.1 с, через релог — точность настенных часов.
Офлайн-разрыв учитывается автоматически.

**1.4 Событие резюма.** При резюме, если офлайн-разрыв > `RESUME_GAP_MIN` (60 с),
писать `gap` c `reason = "login"` и `seconds = <разрыв>`. Даёт вебу основание нарисовать
пунктир вместо прямой линии через полкарты.

**1.5 ID сессии** ([addon/Session.lua:21-24](addon/Session.lua#L21-L24))

`id = time() * 1000 + counter`, где counter — 0..999 внутри одной секунды.
Монотонно, хронологически сортируемо, без «кражи» будущей секунды.
Требует `unsignedBigInteger` на бэке (этап 7).

Дополнительно `WhereIveBeenDB.lastSessionId`: новый id всегда строго больше последнего
выданного. Без этого удаление сессии и старт новой в ту же секунду переиспользуют её id,
а на бэке это коллизия с `UNIQUE(user_id, game_session_id)` уже импортированной сессии.

**1.6 `ClearSession`** ([addon/Session.lua:56](addon/Session.lua#L56)) — вместе с точками
сбрасывать `clock`/`tBase`/`started`, иначе новый маршрут стартует с `t = 3600`.
Дополнительно: флашить буфер агрегации (этап 3).

**1.7 `EndSession`** — проставлять `ended = time()`, обнулять `activeSessionId`,
флашить буфер.

**1.8 Кап и ротация.** Константы:

```lua
WIVBN.MAX_POINTS_SESSION = 10000   -- ~1.2 МБ текста SavedVariables
WIVBN.MAX_POINTS_TOTAL   = 40000   -- ~5 МБ бюджет всей базы
WIVBN.MAX_SESSIONS       = 50
```

- достигнут `MAX_POINTS_SESSION` → `RotateSession()`: текущая закрывается с `ended`,
  открывается новая с `continuesFrom = <prev id>`, уведомление в чат;
- на старте/ротации → `EnforceBudget()`: удаляем самые старые сессии с непустым
  `exportedAt`, пока не влезем в `MAX_POINTS_TOTAL` и `MAX_SESSIONS`. Если бюджет
  превышен, а удалять нечего — громкое предупреждение, **без** тихого удаления
  неэкспортированных данных;
- на старте → разовая чистка сессий с нулём точек (уберёт исторический мусор).

**1.9 `exportedAt`** проставляется в `WIVBN.ShowExportWindow` ([addon/UI.lua:179](addon/UI.lua#L179)).

**1.10 Флаш на выходе.** `PLAYER_LOGOUT` → `EndPending()`: флаш буфера агрегации.
Без этого последний всплеск лута теряется на логауте.

---

## Этап 2. Исправление существующих событий

**2.1 Потеря количества в луте** ([addon/Core.lua:71](addon/Core.lua#L71))

Паттерн `"|hx(%d+)"` не совпадает никогда: реальный текст — `...|h[Item]|h|rx3.`,
между `|h` и `x` стоит `|r`. Итог — `count` всегда 1.

**2.2 Локализация парсинга лута** ([addon/Core.lua:67](addon/Core.lua#L67))

`text:find("You receive")` работает только на enUS. Строим паттерны из глобалок один раз
при загрузке:

```lua
local function pattern(globalString)
    return "^" .. globalString:gsub("([%^%$%(%)%%%.%[%]%*%+%-%?])", "%%%1")
                              :gsub("%%%%s", "(.+)")
                              :gsub("%%%%d", "(%%d+)")
end
-- LOOT_ITEM_SELF, LOOT_ITEM_SELF_MULTIPLE,
-- LOOT_ITEM_PUSHED_SELF, LOOT_ITEM_PUSHED_SELF_MULTIPLE
```

Порядок важен: `_MULTIPLE` проверяем первым, иначе одиночный паттерн съест строку с количеством.

**2.3 Рейд ломает трекинг группы** ([addon/Methods.lua:90-100](addon/Methods.lua#L90-L100))

`GetNumSubgroupMembers()` в рейде возвращает 0 — юниты `party1..4` там nil, ростер
схлопывается до самого игрока. Нужна ветка:

```lua
if IsInRaid() then
    for i = 1, GetNumGroupMembers() do  unit = "raid"..i  end
else
    for i = 1, GetNumSubgroupMembers() do  unit = "party"..i  end
end
```

**2.4 Свой персонаж в событиях группы** ([addon/Methods.lua:93](addon/Methods.lua#L93))

Убрать игрока из ростера — сейчас вход в пати даёт `group join <я>`, выход — `group leave <я>`.
Имена квалифицировать реалмом (`UnitFullName`), иначе кросс-реалмовые тёзки схлопываются
в одного.

Пересинхронизировать `groupRoster` в `PLAYER_ENTERING_WORLD` — иначе на каждом логине
приходит пачка «все зашли». Схлопывание (этап 3) сожмёт её в одно событие, но лучше не
генерировать вовсе.

**2.5 Состояние такси** ([addon/Core.lua:111-117](addon/Core.lua#L111-L117))

`UnitOnTaxi("player")` в момент `PLAYER_CONTROL_LOST` может быть ещё `false`, а сами события
летят на любой стан и катсцену. Переносим проверку в тик `OnUpdate` рядом с проверкой маунта,
опрос раз в ~0.5 с. `PLAYER_CONTROL_LOST/GAINED` из регистрации убираем.

**2.6 Молчаливая потеря событий** ([addon/Methods.lua:30-35](addon/Methods.lua#L30-L35))

`SavePosition` выходит, если карта недоступна, а `SaveEvent` идёт через неё — значит смерть,
лут, левелап и вход в инстанс теряются ровно в те моменты, когда карта не готова.

Разделяем ответственность:

```lua
WIVBN.SavePosition(extra)   -- для семплинга: нет позиции → выход
WIVBN.SaveEvent(extra)      -- для событий: нет позиции → фолбэк на lastMapId/lastX/lastY,
                            --               нет и его → точка с mapId=nil, x=nil, y=nil
```

В схеме БД события живут по `sequence`, координаты им не обязательны:
`way_points.map_id` уже nullable, `x`/`y` при отсутствии пишутся нулями.
Событие с потерянной координатой лучше, чем отсутствующее событие.

**2.7 `gap` при смене мира** ([addon/Core.lua:52-60](addon/Core.lua#L52-L60))

Пишется прямо в `PLAYER_ENTERING_WORLD`, то есть в момент, когда карта гарантированно
не готова, и глушится багом 2.6. После фикса 2.6 событие перестанет теряться; дополнительно
уточняем координату через `C_Timer.After(0.5, ...)` — если позиция к тому моменту доступна,
дописываем её в уже созданную точку.

---

## Этап 3. Схлопывание событий (`Aggregate.lua`)

Схема требует схлопывания сама: `events` имеет PK `(game_session_id, sequence)`, то есть
физически одно событие на точку. Сейчас три предмета из одного трупа выживают только
потому, что аддон пишет три точки с почти одинаковыми координатами.

**3.1 Буфер**

```lua
WIVBN.pending = {
    kind   = "loot",             -- тип агрегата
    anchor = { mapId, x, y, t }, -- позиция первого элемента всплеска
    data   = { ... },            -- накопленное
    timer  = <C_Timer handle>,
}
```

**3.2 API**

- `WIVBN.SaveEvent(extra)` — немедленная запись, как сейчас (переходы состояния);
- `WIVBN.PushAggregated(kind, entry)` — в буфер;
- `WIVBN.FlushPending()` — записать буфер одной точкой по `anchor`.

**3.3 Условия флаша**

- истечение окна `AGGREGATE_WINDOW = 1.0` с (`GATHER_WINDOW = 3.0` для сбора);
- `LOOT_CLOSED`;
- push другого `kind`;
- **любой немедленный `SaveEvent`** — флашим буфер первым, иначе отложенная запись
  встанет в `points` после более позднего события и сломает хронологию `t`/`seq`;
- смещение позиции больше `MIN_MOVE` (проверяется в тике);
- `EndSession`, `ClearSession`, `RotateSession`, `PLAYER_LOGOUT`.

**3.4 Что агрегируем**

| Событие | Агрегация | Форма payload |
|---|---|---|
| `loot` | да, на труп/контейнер | `items: [{id, name, n}]` |
| `gather` | да, узел + его лут | `node: {name, objectId, prof}`, `items: [...]` |
| `group` | да, дифф ростера | `joined: [...]`, `left: [...]` |
| `visit` | да, один NPC | `places: [...]`, `npcId`, `npcName` |
| `combat`, `mount`, `taxi`, `death`, `resurrect`, `levelup`, `quest`, `gap`, `zone` | нет | переходы состояния, точечные по смыслу |

**3.5 Обратная совместимость.** Старые логи содержат плоские `item_name`/`count`/`member`/`place`.
Валидатор и `DescribeEvent` на бэке должны понимать обе формы — существующие фикстуры
`valid1.txt`/`valid2.txt` обязаны продолжать импортироваться.

---

## Этап 4. Новые события

**4.1 Переходы между зонами → тип `zone`**

События: `ZONE_CHANGED_NEW_AREA`, `ZONE_CHANGED`, `ZONE_CHANGED_INDOORS`.
Пишем **только** при смене зоны или `mapId`; подзону кладём полем `subZone` на ту же точку,
а не отдельным событием — иначе прогулка по Оргриммару даст два десятка маркеров.
Дедуп по паре `(zone, subZone)`, эти события приходят пачками.

```json
{ "event": "zone", "zone": "Elwynn Forest", "subZone": "Goldshire", "mapId": 37 }
```

**4.2 Сбор ресурсов и рыбалка → тип `gather`**

`RegisterUnitEvent("UNIT_SPELLCAST_SUCCEEDED", "player")`, аргументы `(unit, castGUID, spellID)`.
Опознаём по таблице `spellID → профессия` (id локале-независимы и стабильны):
Herbalism / Mining / Skinning / Fishing / Opening, все ранги от Vanilla до Retail.

Корреляция с лутом: успешный каст сбора взводит окно `GATHER_WINDOW = 3` с; пришедший
в это окно `LOOT_OPENED` попадает не в `loot`, а в `gather`. Идентичность узла берём из
`GetLootSourceInfo(slot)` — GUID вида `GameObject-0-…-<objectId>-…`, оттуда `objectId`
(локале-независим), имя — best-effort из заголовка окна лута.

```json
{ "event": "gather", "node": {"name": "Copper Vein", "objectId": 1731, "prof": "mining"},
  "items": [{"id": 2770, "name": "Copper Ore", "n": 2}] }
```

> Требует проверки в игре: формат GUID источника лута и наличие `GetLootSourceInfo`
> на всех целевых флейворах. Если на Vanilla функции нет — фолбэк на имя из
> `LootFrameTitleText` без `objectId`.

**4.3 Кто убил → расширение payload `death`** (`Tracking.lua`)

`COMBAT_LOG_EVENT_UNFILTERED`, кольцевой буфер последних N входящих уронов.
Обрабатываем `SWING_DAMAGE`, `SPELL_DAMAGE`, `SPELL_PERIODIC_DAMAGE`, `RANGE_DAMAGE`,
`ENVIRONMENTAL_DAMAGE`. На `PLAYER_DEAD` берём последний.

```json
{ "event": "death", "killer": {"name": "Kobold Miner", "npcId": 1412,
  "spell": "Melee", "amount": 148, "pvp": false} }
```

Для `ENVIRONMENTAL_DAMAGE` вместо киллера — `environment: "FALLING" | "DROWNING" | "LAVA" | …`.
PvP определяем по префиксу GUID (`Player-…`).

> Перф: CLEU — самое высокочастотное событие в игре, фильтровать его нельзя.
> Обязателен ранний выход: сразу после `CombatLogGetCurrentEventInfo()` сравниваем
> `destGUID` с закешированным `UnitGUID("player")` и выходим. Всё остальное — только
> для своих уронов.

**4.4 Расширение `visit`**

| Событие | `place` | Примечание |
|---|---|---|
| `MERCHANT_SHOW` | `merchant` (+ `repair`, если `CanMerchantRepair()`) | оба place в одном событии |
| `BANKFRAME_OPENED` | `bank` | есть |
| `AUCTION_HOUSE_SHOW` | `auction` | есть |
| `MAIL_SHOW` | `mail` | |
| `TRAINER_SHOW` | `trainer` | |
| `TAXIMAP_OPENED` | `flightmaster` | |
| `GUILDBANKFRAME_OPENED` | `guildbank` | нет в Vanilla/TBC → `SafeRegister` |
| `PET_STABLE_SHOW` | `stable` | → `SafeRegister` |
| `BARBER_SHOP_OPEN` | `barber` | нет в Vanilla → `SafeRegister` |
| `TRADE_SHOW` | `trade` | + имя партнёра |

Ко всем — `npcId` из `UnitGUID("npc")` (префикс `Creature-0-…-<id>-…`) и `npcName`.
`npcId` даёт вебу локале-независимую идентичность POI между сессиями и персонажами.

**4.5 Причина `gap` через `UNIT_SPELLCAST_SUCCEEDED`**

Таблица известных телепортов (Hearthstone, порталы и телепорты мага, Dreamwalk,
призывы варлока, свисток мастера полётов, инженерные wormhole) **плюс** эвристика:
любой успешный каст игрока запоминается на 20 с; если в это окно приходит
`PLAYER_ENTERING_WORLD` или позиция прыгает дальше порога — каст приписывается `gap`.
Эвристика покрывает заклинания, которых нет в таблице.

```json
{ "event": "gap", "reason": "spell", "spellId": 8690, "spellName": "Hearthstone" }
```

Значения `reason`: `spell`, `taxi`, `death`, `login`, `loading`.

---

## Этап 5. Экспорт

**5.1 Схема экспорта v2**

```json
{
  "schema": 2,
  "sessionId": 1757145600123,
  "continuesFrom": null,
  "started": 1757145600,
  "ended": 1757152800,
  "char": "Name", "realm": "Realm",
  "faction": "Horde", "class": "WARRIOR", "level": 42,
  "locale": "enUS",
  "addon": "2.0.0",
  "gameVersion": "11.2.7",
  "build": "58238",
  "version": 110207,
  "points": [ ]
}
```

Закрывает *сохранение версии*: сейчас в поле `version` летит `tocversion`
([addon/Export.lua:8](addon/Export.lua#L8)) — это ни версия аддона, ни версия игры.
`version` оставляем как есть ради совместимости с существующей колонкой, реальные
данные приходят отдельными полями. Версию аддона берём из метаданных TOC через
шим `C_AddOns.GetAddOnMetadata or GetAddOnMetadata`.

**5.2 Транспорт**

```
json → LibDeflate:CompressDeflate(json) → Base64.Encode(...) → "WIVB1:" .. payload
```

JSON сжимается в 8–12 раз: 600 КБ → 60–80 КБ, одно окно копипаста без фризов
`SetText`/`HighlightText`. Сырой JSON остаётся доступен командой `/wivbn raw`
для отладки и для генерации фикстур.

**5.3 Пустая сессия** не экспортируется: dkjson кодирует пустой `points = {}` как `{}`,
а на бэке `points` — `required|array`, импорт падает. Проверку ставим в
`BuildSessionExport` с внятным сообщением.

---

## Этап 6. UI и команды

**6.1 Скрыть пустые сессии** — фильтр `#s.points > 0` в `CollectSessionIds`
([addon/UI.lua:41-49](addon/UI.lua#L41-L49)).

**6.2 Удаление конкретной сессии** — правый клик по строке
([addon/UI.lua:109-111](addon/UI.lua#L109-L111)) → `StaticPopup` с подтверждением →
удаление. `row:RegisterForClicks("LeftButtonUp", "RightButtonUp")`. Плюс команда
`/wivbn delete <id>`.

**6.3 Метки в списке** — показывать `continuesFrom` («part 2») и признак `exportedAt`,
чтобы было видно, что можно удалять без потерь.

**6.4 Справка** ([addon/Commands.lua:24](addon/Commands.lua#L24)) — отдельная `help`,
пустая команда печатает справку, а не ошибку. Добавить пропущенный `clear_all`,
новые `delete`, `raw`. Неизвестная команда → короткая ошибка + подсказка `/wivbn help`.

**6.5 TOC**

- `WhereIveBeen.toc` сейчас дублирует `_TBC.toc` (Interface 20506) — generic-файл должен
  зеркалить Mainline (110207);
- добавить `Libs\LibDeflate.lua`, `Libs\Base64.lua`, `Aggregate.lua`, `Tracking.lua`
  во все семь TOC в правильном порядке;
- `## Version: 2.0.0`, `## IconTexture`, `## Category-enUS: Map`.

---

## Этап 7. Бэкенд

Миграции правим **на месте** (проект в разработке, БД пересоздаётся).

**7.1 `create_game_sessions_table.php`**

- `game_session_id`: `integer unsigned` → `unsignedBigInteger` (обязательно под новый ID);
- `+ ended_at` datetime nullable;
- `+ continues_from` unsignedBigInteger nullable;
- `+ addon_version` string(16) nullable;
- `+ schema_version` unsignedTinyInteger default 1;
- `+ locale` string(8) nullable;
- `+ game_version` string(16) nullable.

**7.2 `create_waypoints_table.php`**

- `sequence`: `smallInteger` → `mediumInteger` unsigned;
- `map_id` и `maps.id` **не трогаем** — остаются `smallInteger` unsigned по решению автора;
- `time`: `mediumInteger` → `unsignedInteger`. Переходим на децисекунды (п. 7.5);
  mediumint в децисекундах — это всего 19 суток, а сессия живёт до ротации;
- `x` / `y` **не трогаем** — `smallInteger` здесь не ограничение, а само кодирование
  координаты (×65535, мутаторы в [WayPoint.php:33-49](web/app/Models/WayPoint.php#L33-L49)).

**7.3 `create_events_table.php`** — `sequence`: `smallInteger` → `mediumInteger` unsigned.
Обязательно синхронно с `way_points`, иначе join по `(game_session_id, sequence)` в
[ShowSessionEvent.php:56-59](web/app/Actions/GameSession/ShowSessionEvent.php#L56-L59) поедет.

**7.4 `EventTypeEnum`** — `+ ZONE = 12`, `+ GATHER = 13`, slug/label/`hasMarker`.
`EventTypeSeeder` подхватит автоматически (он строится из `cases()`).

**7.5 Подсчёт `t`** — [CreateWayPointDTO.php:24](web/app/DTOs/GameSession/CreateWayPointDTO.php#L24)

Сейчас `(int) $point['t']` отбрасывает десятые, хотя [plan.md](web/plan.md) требует
децисекунды. Меняем на `(int) round($point['t'] * 10)`.

**Хвост, который обязан поехать следом:**
[ShowSessionEvent.php:78](web/app/Actions/GameSession/ShowSessionEvent.php#L78) делает
`addSeconds((int) $time)` — после перехода на децисекунды это `addMilliseconds($time * 100)`.
Без этой правки время события на фронте станет в 10 раз больше.

**7.6 `DecodeRawInput`**

- ветка сжатия: строка начинается с `WIVB1:` → `base64_decode` → `gzinflate` → `json_decode`.
  Обе функции встроенные, зависимостей нет. Сырой JSON (начинается с `{`) — старый путь;
- **поднять глубину** `json_decode($input, true, 4)` → 6. Со схлопыванием появляется
  уровень `root → points → point → items → item`, на текущей глубине 4 импорт падает;
- ошибки декодирования/инфлейта → `InvalidGameSessionInputException` с внятным текстом.

**7.7 `GameSessionJsonValidator`** — новые поля сессии (`schema`, `ended`, `continuesFrom`,
`addon`, `locale`, `faction`, `class`, `level`, `gameVersion`, `build`) и точки:

```
points.*.items          sometimes|array
points.*.items.*.id     required_with:points.*.items|integer
points.*.items.*.name   sometimes|string
points.*.items.*.n      sometimes|integer
points.*.joined         sometimes|array
points.*.left           sometimes|array
points.*.places         sometimes|array
points.*.npcId          sometimes|integer
points.*.zone           sometimes|string
points.*.subZone        sometimes|string
points.*.node           sometimes|array
points.*.killer         sometimes|array
points.*.environment    sometimes|string
points.*.reason         sometimes|string
points.*.spellId        sometimes|integer
```

Старые плоские поля (`item_name`, `count`, `member`, `place`) **остаются** — фикстуры
`valid1.txt`/`valid2.txt` должны импортироваться без правок.

**7.8 `CreateEventDTO::fromPoint`** ([CreateEventDTO.php:19-45](web/app/DTOs/GameSession/CreateEventDTO.php#L19-L45))

Сейчас это плоский маппинг фиксированных ключей. Добавить проброс массивов/объектов
(`items`, `joined`, `left`, `places`, `node`, `killer`) без разворачивания.

**7.9 `CreateGameSessionDTO` + `CreateGameSession` + модель `GameSession`** — новые поля,
`fillable`, касты (`ended_at` → datetime).

**7.10 Убрать битую связь** — [WayPoint.php:17-20](web/app/Models/WayPoint.php#L17-L20)

`hasOne(Event::class, 'sequence', 'sequence')` не фильтрует по `game_session_id`: связь
цепляет события чужих сессий с тем же `sequence`. Композитный ключ Eloquent через `hasOne`
корректно не выражается (обход через `whereColumn` ломает eager loading). Связь нигде
не используется — удаляем. Доступ к событиям идёт через `GameSession`, как и предписано
[plan.md §3](web/plan.md) («join не нужен даже на клиенте»).

**7.11 `DescribeEvent`** — ветки для `ZONE` и `GATHER`, поддержка новых форм payload
(`items[]`, `joined[]`/`left[]`, `places[]`, `killer{}`) **при сохранении** старых
плоских веток.

---

## Этап 8. Тесты

**8.1 Новые фикстуры:** `valid-v2.txt` (схема 2 со схлопнутыми событиями),
`valid-v2-compressed.txt` (`WIVB1:` payload), `valid-deep.txt` (проверка глубины
`json_decode`). Существующие `valid1/valid2` не трогаем — они гарантия совместимости.

**8.2 `DecoderTest`** — распаковка `WIVB1:`, битый base64, битый deflate, глубина 6,
сырой JSON по-прежнему работает.

**8.3 `GameSessionJsonValidatorTest`** — новые поля, массивы предметов, старая плоская форма.

**8.4 `CreateGameSessionWayTest`** — `t` в децисекундах, `sequence` за пределом
старого smallint (например 40000), событие без координат (фикс 2.6).

**8.5 `DescribeEventTest`** — `zone`, `gather`, схлопнутый `loot`/`group`/`visit`,
и **старые плоские payload'ы** обязаны давать прежний вывод.

**8.6 `ShowSessionEventTest`** — время события после перехода на децисекунды.

**8.7 Прогон:** `composer test` (pint + phpstan level 7 + pest) и `npm run types:check`,
если поедут типы событий на фронте.

---

## Порядок выполнения

Этапы 1–3 связаны и правятся одним заходом: жизненный цикл сессии, фиксы записи точек,
буфер агрегации. Этап 7 идёт следом, потому что схема экспорта к этому моменту
зафиксирована. Этапы 4 (новые события), 5–6 (экспорт, UI) и 8 (тесты) можно вести
параллельно после того, как контракт устоялся.

Единственная жёсткая зависимость: **7.5 и 7.6 нельзя мержить порознь** — переход на
децисекунды без правки `ShowSessionEvent` даст десятикратную ошибку времени на фронте,
а сжатый экспорт без поднятия глубины `json_decode` уронит импорт на первом же
схлопнутом луте.

---

## Выполнено — отклонения от плана

План реализован полностью, девятью коммитами. Ниже то, что по ходу разошлось с планом.

**Детекция сбора (4.2) — без таблицы spellID.** Вместо списка `spellID → профессия`
узел опознаётся по данным самой игры: `IsFishingLoot()` и GUID источника лута вида
`GameObject-…`. Это покрывает траву, руду и сундуки в любой локали и на любом флейворе,
не требуя сотни неверифицируемых id. `prof` заполняется только для рыбалки; id и имя
предшествующего каста кладутся в событие сырыми, профессию доопределяет веб.

**Монотонный `lastSessionId` (1.5).** Всплыло на стенде: после удаления сессии старт
новой в ту же секунду переиспользовал её id. Локально безобидно, на бэке — коллизия
с `UNIQUE(user_id, game_session_id)` уже импортированной сессии.

**`ended_at` назван `session_end_at`** — для симметрии с существующим `session_start_at`.

**Сверх плана добавлены колонки `faction`, `class`, `level`** — аддон эти данные шлёт,
терять их при импорте было бы странно.

**Глубина `json_decode` — проверено, а не выведено.** Тест показал, что **5 тоже мало**:
`root → points → point → items → item` требует ровно 6.

**Второе место с децисекундами.** Кроме `ShowSessionEvent` конвертацию времени делает
и `BuildSessionZones` (`addSeconds` при расчёте времени зоны) — правились вместе.

**Фронтенд (не был в плане).** `EventSlug`, `EVENT_ORDER`, `EVENT_COLORS`, `EVENT_LABELS`
и `EVENT_ICONS` в [web/resources/js/lib/eventStyles.ts](web/resources/js/lib/eventStyles.ts)
не знали про `zone` и `gather` — маркеры остались бы без цвета и иконки.

**Сжатие — 4.6×, не 8–12×.** Измерено на синтетической сессии в 406 точек:
17 809 → 3 882 байт. На реальных данных с повторяющимися названиями предметов
будет лучше, но исходную оценку считать завышенной.

**Страховочный merge в воркере (3.x) не делался** — по решению «схлопывание только в аддоне».

### Проверка

- Lua 5.1 (`luac -p` + три стенда со заглушками WoW API): синтаксис всех файлов,
  жизненный цикл сессии, агрегация, трекинг — 60+ проверок.
- Сквозной цикл аддон → PHP: dkjson → deflate → base64 → `base64_decode` + `gzinflate`
  + `json_decode`, включая кириллицу.
- Бэкенд: 114 тестов зелёных, pint чистый, PHPStan — ноль новых ошибок
  (в репозитории было 40 на level 7 до правок, стало 39).
- Фронтенд: `vue-tsc`, eslint и prettier чистые.

Стенды на Lua лежат в скретчпаде сессии и не сохранены в репозиторий.
