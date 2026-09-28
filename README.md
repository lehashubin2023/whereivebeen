# WhereIveBeen

Трекер маршрутов персонажа в World of Warcraft. Аддон пишет, где ты ходил и что с тобой
происходило, ты выгружаешь сессию в веб — и видишь свой путь на карте зоны плюс статистику
по всем сессиям сразу.

В репозитории две независимые части:

- [`addon/`](addon/) — аддон на Lua. Это единственный источник данных: он ничего не читает
  из сети и никуда не отправляет, всё общение с сайтом идёт через копипаст пользователя.
- [`web/`](web/) — Laravel 13 + Vue 3 на Inertia: импорт, хранение, визуализация.

## Что умеет

В игре аддон пишет точки маршрута (нормализованные координаты UiMap), состояние персонажа
(бой, маунт, такси) и 13 типов событий: смерть с разбором того, кто убил, воскрешение,
левелап, квесты, смену зоны, лут, сбор нод, заход к торговцу или на почту, изменения состава
группы и «гэпы» — телепорты, полёты, загрузочные экраны. Сессия экспортируется в JSON,
жмётся deflate'ом и кодируется base64 с префиксом `WIVB1:` — выходит примерно в 4.6 раза
меньше. Поддерживаются все флейворы от Vanilla до текущего Mainline, по `.toc` на каждый.

Загрузить сессию можно двумя способами: вставить строку экспорта или закинуть целиком файл
`WhereIveBeen.lua` из SavedVariables (до 16 МБ). Файл разбирается собственным потоковым
Lua-парсером, сессии из него складываются в спул и импортируются пачкой, с прогрессом
и статусом по каждой.

Маршрут рисуется своим SVG-рендерером поверх картинки зоны: зум, фуллскрин, сегменты
раскрашены по состоянию, слипшиеся маркеры событий разводятся веером, у каждого — попап
с деталями. Страница `/statistics` показывает готовый снапшот: счётчики, разбивку по зонам,
«путешествие» персонажа.

Вокруг этого — обычная обвязка: локализация (en/ru), SEO-мета с `robots.txt` и `sitemap.xml`,
форма обращений с админской очередью, админка пользователей, страница донатов, сборка
и раздача zip-архива аддона, свои страницы ошибок.

## Как устроен импорт

Аддон копит точки и события в `WhereIveBeenDB`: база account-wide, но активная сессия
привязана к персонажу. Пользователь копирует строку или загружает файл, дальше всё уходит
в очередь — `ImportGameSessionJob` или `ImportSavedVariablesFileJob`.

Джоба декодирует ввод, прогоняет его через валидатор, заводит неизвестные карты как
`Zone {id}` (их id уезжают в предупреждения) и одной транзакцией пишет сессию, точки
и события chunked-инсертами. Тем же проходом по массиву точек собираются факты статистики —
отдельных запросов ради этого не делается.

После успешного импорта ставится `RebuildUserStatisticsJob`, который пересобирает снапшот
`user_statistics`. Он `ShouldBeUniqueUntilProcessing` и с задержкой в 5 секунд, так что
целый файл сессий схлопывается в один пересчёт. Каждый импорт пишется в `import_logs`
и `import_batches` — статус, прогресс, время, код ошибки.

## Решения, которые стоит знать

**Actions + DTO.** Одна операция — один класс с `exec()`, зависимости через конструктор.
Actions ничего не знают про HTTP, контроллеры остаются тонкими: [app/Actions](web/app/Actions),
[app/DTOs](web/app/DTOs).

**Всё, что пришло из аддона, считается враждебным.** Строка проходит через
[GameSessionJsonValidator](web/app/Validators/GameSessionJsonValidator.php), файл — через
собственный лексер и парсер ([app/Support/Lua](web/app/Support/Lua)) с жёсткими лимитами
на размер, глубину, количество таблиц, сессий и точек (`LuaParseLimits`). Ошибки импорта
переводятся в коды через `MapImportFailure`, сырые сообщения наружу не уходят.

**Контракты вместо ветвлений.** `ImportSourceContract` (строка или спул),
`ImportGameSessionProgressContract` с Null-объектом, `PointSourceContract` (точки из импорта
или уже из БД). Благодаря последнему один и тот же конвейер работает и на импорте,
и на пересчёте задним числом.

**Статистика считается заранее, в два слоя.** Сначала факты по сессии —
`session_statistic_entries`, `session_event_counts`, `session_map_stats`; переимпорт сессии
переписывает только её факты. Потом проекция на пользователя в `user_statistics`.
Снапшот хранится локаль-нейтрально: слаги вместо переводов, `map_id` вместо имён карт,
секунды вместо «1h 20m» — переводы и форматирование навешивает `ReadUserStatistics`
уже на чтении. Сами считалки разложены по событиям в сборщики и собираются
через `CollectorRegistry`, а схема фактовых таблиц описана декларативно в `TableCatalog`.

**Схема БД под чтение маршрута.** Кластерный PK `way_points(session_id, seq)` превращает
чтение трека в range-scan. `state` продублирован в точку, чтобы раскрашивать линию без join.
События координат не хранят — берут их по `seq`. От повторов защищает
`UNIQUE(user_id, game_session_id)`.

**Роуты живут рядом с кодом.** `spatie/laravel-route-attributes` — `#[Get]`, `#[Group]`,
`#[Middleware]` прямо на контроллерах, отдельного `web.php` нет. На фронт роуты и экшены
отдаёт Wayfinder, генерируя типизированный TS в `resources/js/actions` и `resources/js/routes`;
руками эти файлы не трогаем.

**Очереди.** Horizon с отдельными супервизорами на `import`, `import-file` и `statistics`,
чтобы разбор большого файла не блокировал обычные импорты. Оставшиеся спул-файлы подчищает
`imports:prune-spool`.

Из мелкого, но важного: лимиты на импорт, обращения, смену пароля и письма верификации
(в том числе по почтовому ящику, а не только по IP) — в `AppServiceProvider`;
`Model::shouldBeStrict()`, `CarbonImmutable` и `DB::prohibitDestructiveCommands()` в проде;
аутентификация на Fortify, приватный контур закрыт `verified`, админ и обычный пользователь
разведены middleware `admin` и `deny-admins`.

## Стек

PHP 8.3+ и Laravel 13, MySQL 8.4, Redis с Horizon, Inertia 3, Vue 3 на TypeScript,
Tailwind CSS v4, компоненты в стиле shadcn на reka-ui, Vite. Качество держат Pest 5, Pint,
Larastan на седьмом уровне, ESLint, Prettier и vue-tsc. Локально всё поднимается в Docker:
nginx, php-fpm, mysql, эфемерный mysql-test, redis и horizon.

## Как развернуть

Все команды — из [`web/`](web/).

```bash
cp .env.example .env          # DB_CONNECTION=mysql, DB_HOST=mysql, REDIS_HOST=redis
make build && make up
make enter-app
```

Дальше внутри контейнера:

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed           # типы событий, карты, админ из MAIN_ADMIN_*
npm install && npm run build
```

Сайт поднимется на `http://localhost:${NGINX_PORT:-8081}`, Horizon — на `/horizon`.

Одна вещь, о которой легко забыть: `maps:import` (его дёргает сидер) ждёт CSV-экспорт
`UiMap.db2` в формате `ID;Name_lang` по пути `storage/app/private/UiMap.csv`. В репозитории
его нет, положи сам. Картинки зон лежат в `public/maps`, имя файла должно совпадать
с именем зоны.

Zip аддона для страницы загрузки собирается одноразовым контейнером — каталог `addon/`
лежит вне тома, поэтому его монтируем отдельно:

```bash
docker compose run --rm -v $(pwd)/../addon:/addon:ro \
    app php artisan addon:package --source=/addon
```

Данные для скриншотов лендинга: `php artisan db:seed --class=DemoSeeder`.

## Команды

```bash
make up / down / build / restart
make enter-app                # bash в контейнере app
make test                     # поднять mysql-test и прогнать php artisan test
make logs-horizon

composer dev                  # дев-среда (artisan dev)
composer test                 # config:clear + pint --test + phpstan + тесты
composer lint                 # pint --parallel с автофиксом
composer ci:check             # eslint + prettier + vue-tsc + тесты

npm run dev / build / lint / types:check

php artisan statistics:rebuild [--user=ID]   # пересобрать факты и снапшоты
php artisan imports:prune-spool [--hours=24] # почистить спул и загрузки
```

Тесты идут против контейнера `mysql-test` — он на tmpfs и умирает вместе с остановкой,
конфигурация в [phpunit.xml](web/phpunit.xml). Фикстуры сессий — в [tests/Fixtures](web/tests/Fixtures).

## Установка аддона

Скопировать `addon/` в `Interface/AddOns/WhereIveBeen` или скачать zip со страницы `/addon`.
В игре: `/wivb start | end | status | get_sessions | log | help`. `get_sessions` открывает
список сессий, левый клик — экспорт строки, правый — удаление.
