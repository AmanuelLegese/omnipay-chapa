# Thin wrapper over docker-compose.yml - each target is one compose command.
# Using compose directly works just as well:  docker compose run --rm test
#
# Needs a running daemon:  sudo systemctl start docker

DC := docker compose

.PHONY: build install update test style fix shell clean

build:
	$(DC) build

install:
	$(DC) run --rm composer install

update:
	$(DC) run --rm composer update

test:
	$(DC) run --rm test

style:
	$(DC) run --rm style

fix:
	$(DC) run --rm fix

shell:
	$(DC) run --rm shell

clean:
	$(DC) down --volumes --rmi local
