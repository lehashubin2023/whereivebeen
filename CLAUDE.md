# CLAUDE.md

## Что это

**WhereIveBeen** — трекер маршрутов персонажа в World of Warcraft. Аддон записывает
перемещения игрока в игре, экспортирует лог, пользователь загружает его в веб-приложение,
где маршрут парсится и (по плану) визуализируется на карте.

Репозиторий состоит из двух независимых частей:

- [`addon/`](addon/) — аддон для WoW на **Lua** (собирает данные в игре).
- [`web/`](web/) — веб-приложение на **Laravel 13 + Vue 3 (Inertia)** (импорт, хранение, визуализация).

Опорная спецификация архитектуры конвейера и схемы БД — [web/plan.md](web/plan.md). Читай её
перед изменениями в доменной логике импорта.

## Поток данных (end-to-end)

1. Аддон пишет точки маршрута и события в `WhereIveBeenDB` (SavedVariables).
   См. [addon/Core.lua](addon/Core.lua), [addon/Session.lua](addon/Session.lua), [addon/Methods.lua](addon/Methods.lua).
2. Экспорт сессии: компактный JSON через [addon/Export.lua](addon/Export.lua) (`WIVBN.ExportSession`) —
   строка отдаётся как есть, без обёртки (копипаст из `EditBox`).
3. Пользователь POST'ит строку на `game-session/import` → [GameSessionController](web/app/Http/Controllers/GameSessionController.php)
   диспатчит [ImportGameSessionJob](web/app/Jobs/ImportGameSessionJob.php) в очередь `import`.
4. Джоба вызывает [ImportGameSession](web/app/Actions/GameSession/ImportGameSession.php):
   `DecodeRawInput` (`trim` + `json_decode`, глубина 4) → `GameSessionJsonValidator` → в транзакции
   `CreateGameSession` + `CreateWay` (chunked-insert точек и событий).
5. Каждый импорт логируется в `import_logs` ([ImportStatusEnum](web/app/Enums/GameSession/ImportStatusEnum.php)).

## Архитектура бэкенда (web/)

Доменная логика организована вокруг **Actions** (одна операция = один класс с `exec()`),
данные передаются через **DTO**, а строковые типы событий из аддона мостятся в
[EventTypeEnum](web/app/Enums/GameSession/EventTypeEnum.php) (`fromSlug`/`slug`).

- [app/Actions/GameSession/](web/app/Actions/GameSession/) — бизнес-операции. Actions чистые,
  не знают про HTTP; инъектятся через конструктор.
- [app/DTOs/GameSession/](web/app/DTOs/GameSession/) — `fromArray`/`fromPoint` → `toArray`.
- [app/Models/](web/app/Models/) — `GameSession`, `WayPoint`, `Event`, `EventType`, `Map`, `ImportLog`, `User`.
- [app/Validators/](web/app/Validators/), [app/Exceptions/GameSession/](web/app/Exceptions/GameSession/) — валидация недоверенного lua-ввода.
- **Роутинг**: контроллеры используют атрибуты `spatie/laravel-route-attributes`
  (`#[Get]`, `#[Post]`, `#[Group]`, `#[Middleware]`); простые страницы — в [routes/web.php](web/routes/web.php)
  и [routes/settings.php](web/routes/settings.php) (`Route::inertia`).
- **Аутентификация**: Laravel **Fortify** ([app/Actions/Fortify/](web/app/Actions/Fortify/)).
- **Wayfinder**: генерит типизированные JS-экшены/роуты в [resources/js/actions/](web/resources/js/actions/)
  и [resources/js/routes/](web/resources/js/routes/) — **не редактировать руками**.

### Схема БД (вариант 1b из plan.md)

Плотное хранение в MySQL, без файлов/blob. Кластерный PK `waypoints(session_id, seq)` →
чтение маршрута = range-scan. `state` (бой/маунт) протаскивается на точку для раскраски
без join. `events` без координат (берутся по `seq`), payload — JSON. Уникальность
импорта: `UNIQUE(user_id, game_session_id)`. Миграции: [database/migrations/](web/database/migrations/).

## Фронтенд (web/resources/js)

Vue 3 + TypeScript + Inertia, Tailwind CSS v4, компоненты в стиле shadcn-vue на `reka-ui`
([components/ui/](web/resources/js/components/ui/)). Страницы — [pages/](web/resources/js/pages/), лейауты — [layouts/](web/resources/js/layouts/).

## Команды (запускать из web/)

Разработка идёт в Docker (`Makefile` в [web/](web/)):

```bash
make up            # поднять контейнеры (app, nginx, mysql, mysql-test)
make enter-app     # bash внутри контейнера app
make test          # поднять тестовую БД и прогнать php artisan test
make down / build / restart
```

Внутри контейнера / локально:

```bash
composer dev        # запуск дев-среды (artisan dev)
composer test       # config:clear + pint --test + phpstan + artisan test
composer lint       # pint --parallel (автофикс)
composer ci:check   # eslint + prettier + vue-tsc + тесты (полный CI)

npm run dev         # vite
npm run build       # сборка (build:ssr — с SSR)
npm run lint        # eslint --fix
npm run types:check # vue-tsc --noEmit
```

## Тесты и качество

- **Pest 5** ([tests/Feature/GameSession/](web/tests/Feature/GameSession/) покрывают импорт/валидацию/парсинг).
  Фикстуры сессий: [tests/Fixtures/game-sessions/](web/tests/Fixtures/game-sessions/).
- Тесты гоняются против контейнера `mysql-test` (tmpfs, эфемерный) — см. [phpunit.xml](web/phpunit.xml).
- **Pint** (preset laravel), **Larastan/PHPStan level 7** ([phpstan.neon](web/phpstan.neon)),
  ESLint + Prettier + vue-tsc для фронта.

## Конвенции

- PHP 8.3, строгие типы. Новую доменную логику оформляй как **Action + DTO**, а не в контроллере.
- Строковые события аддона всегда мапь через `EventTypeEnum::fromSlug()` — не хардкодь id.
- Lua-экспорт — недоверенный ввод: любой новый парсинг должен валидироваться.
- Wayfinder-генерённый JS и `vendor/`, `node_modules/` — не трогать.

## Известные шероховатости

- Часть архитектуры из [plan.md](web/plan.md) (SSE-статусы, рендеринг на Leaflet, раскраска
  по `state`) ещё не реализована — сверяйся с планом, что уже есть, а что нет.
