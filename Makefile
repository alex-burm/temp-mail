up:
	@docker compose up --build

down:
	@docker compose down

messenger:
	@docker compose exec php bin/console messenger:consume --all -vv

migrate:
	@docker compose exec php bin/console doctrine:migrations:migrate -n

migration:
	@docker compose exec php bin/console doctrine:migrations:generate

bash:
	@docker compose exec php bash

sh:
	@docker compose exec php bash
