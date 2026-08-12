#!/bin/bash
# À lancer sur le serveur EC2, depuis /opt/axiobad, pour déployer une nouvelle version.
set -euo pipefail

cd "$(dirname "$0")/../.."

COMPOSE="docker compose -f compose.yaml -f compose.prod.yaml -f compose.demo.yaml --env-file .env.prod.local --env-file .env.demo.local"

git pull origin main
$COMPOSE up -d --build
$COMPOSE exec -u www-data php bin/console doctrine:migrations:migrate --no-interaction
$COMPOSE exec -u www-data php bin/console cache:clear
$COMPOSE exec -u www-data php-demo bin/console doctrine:migrations:migrate --no-interaction
$COMPOSE exec -u www-data php-demo bin/console cache:clear
# Filet de sécurité : si un exec précédent (root) a laissé des fichiers de cache mal owned, on corrige.
$COMPOSE exec -u root php chown -R www-data:www-data /var/www/html/var
$COMPOSE exec -u root php-demo chown -R www-data:www-data /var/www/html/var

# nginx monte sa conf en bind-mount (pas dans l'image) : "up -d --build" ne le recrée donc pas
# quand seul le contenu du fichier change sur le host, et il ne relit pas sa conf tout seul —
# un reload est nécessaire à chaque déploiement. Important en particulier parce que nginx résout
# les noms "php"/"php-demo" au démarrage : sans redémarrage/reload régulier, une IP libérée par un
# conteneur recréé peut être réattribuée à l'autre au déploiement suivant, et nginx continuerait
# de parler à la mauvaise IP (voir le commentaire dans docker/nginx/prod.conf).
$COMPOSE exec nginx nginx -t
$COMPOSE exec nginx nginx -s reload
