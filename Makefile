lint:
	docker run -it --init --rm \
		-v $(PWD):/code \
		-w /code \
		ekreative/php-cs-fixer php-cs-fixer fix

test:
	docker-compose run --rm tests
