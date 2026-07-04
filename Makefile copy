 # Makefile
install:
	@composer install --ignore-platform-reqs
update:
	@composer update --ignore-platform-reqs
dev:
	@php -S localhost:8000 -t public/

debug:
	@sudo sed -i '' 's/xdebug.start_with_request = trigger/xdebug.start_with_request = yes/' /opt/local/etc/php83/php.ini
	@echo "Xdebug start_with_request set to 'yes'"
	@php -S localhost:8000 -t public/

no-debug:
	@sed -i '' 's/xdebug.start_with_request = yes/xdebug.start_with_request = trigger/' /opt/local/etc/php83/php.ini
	@echo "Xdebug start_with_request set to 'trigger'"
	@php -S localhost:8000 -t public/

