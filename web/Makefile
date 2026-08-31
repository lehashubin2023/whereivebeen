enter-app:
	docker compose -f docker-compose.yml exec -it app /bin/bash

enter-app-root:
	docker compose -f docker-compose.yml exec -u root -it app /bin/bash

enter-horizon:
	docker compose -f docker-compose.yml exec -u root -it horizon /bin/bash

up:
	docker compose -f docker-compose.yml up -d

down:
	docker compose -f docker-compose.yml down

build:
	docker compose -f docker-compose.yml build

restart:
	docker compose -f docker-compose.yml restart

logs-horizon:
	docker compose -f docker-compose.yml logs -f horizon

restart-horizon:
	docker compose -f docker-compose.yml restart horizon

horizon-status:
	docker compose -f docker-compose.yml exec app php artisan horizon:status

horizon-terminate:
	docker compose -f docker-compose.yml exec app php artisan horizon:terminate

up-test-db:
	docker compose -f docker-compose.yml up -d mysql-test

test: up-test-db
	docker compose -f docker-compose.yml exec -T app php artisan test