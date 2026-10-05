.PHONY: phpunit phpstan ecs lint-container test-unit test-integration test-functional behat \
	docker-up docker-down test-app-init test-app-frontend behat-docker

DOCKER_PHP = docker compose exec -T php

phpunit:
	vendor/bin/phpunit --colors=always

test-unit:
	vendor/bin/phpunit --colors=always --testsuite=unit

test-integration:
	vendor/bin/phpunit --colors=always --testsuite=integration

test-functional:
	vendor/bin/phpunit --colors=always --testsuite=functional

behat:
	vendor/bin/behat --strict --no-interaction -f progress

phpstan:
	vendor/bin/phpstan analyse --memory-limit=1G

ecs:
	vendor/bin/ecs check

lint-container:
	vendor/bin/console lint:container

## Ephemeral Docker stack for Behat (MySQL + PHP + nginx)
docker-up:
	@test -f compose.override.yml || cp compose.override.dist.yml compose.override.yml
	docker compose up -d

docker-down:
	docker compose down -v

test-app-init:
	$(DOCKER_PHP) vendor/bin/console doctrine:database:create --if-not-exists
	$(DOCKER_PHP) vendor/bin/console doctrine:migrations:migrate -n
	$(DOCKER_PHP) vendor/bin/console assets:install vendor/sylius/test-application/public

test-app-frontend:
	docker run --rm -v "$(CURDIR):/app" -w /app/vendor/sylius/test-application node:22-alpine \
		sh -c "corepack enable && yarn install && yarn build"

behat-docker:
	$(DOCKER_PHP) vendor/bin/behat --strict --no-interaction -f progress
