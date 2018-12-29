.PHONY: lint test coveralls

vendor: composer.json
	docker-compose run --no-deps --rm tests composer install

lint:
	docker run -it --init --rm \
		-v $(PWD):/code \
		-w /code \
		ekreative/php-cs-fixer php-cs-fixer fix

test: vendor
	docker-compose run --no-deps --rm tests ./vendor/bin/phpunit
