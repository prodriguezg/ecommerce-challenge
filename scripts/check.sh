#!/bin/sh
set -eu

composer validate --strict --working-dir=services/commerce-api
composer validate --strict --working-dir=services/payment-api
services/commerce-api/vendor/bin/pint --test
services/payment-api/vendor/bin/pint --test
services/commerce-api/vendor/bin/phpstan analyse --configuration=services/commerce-api/phpstan.neon --memory-limit=1G --debug
services/payment-api/vendor/bin/phpstan analyse --configuration=services/payment-api/phpstan.neon --memory-limit=1G --debug
composer test --working-dir=services/commerce-api -- --compact
composer test --working-dir=services/payment-api -- --compact
npm run check
docker compose --env-file .env.example config --quiet
