#!/bin/sh
set -e

php /var/www/html/bin/migrate.php
php /var/www/html/seeds/seed.php

exec "$@"
