#!/bin/sh
set -e

# Certains hébergeurs imposent le port d'écoute via la variable PORT
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

# Mise à jour du schéma de la base à chaque déploiement
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

php bin/console cache:clear --no-warmup
php bin/console cache:warmup
chown -R www-data:www-data var

exec "$@"
