#!/bin/bash
# À lancer sur le serveur EC2, depuis /opt/axiobad, pour déployer une nouvelle version.
set -euo pipefail

cd "$(dirname "$0")/../.."

git pull origin main
docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local up -d --build
docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local exec php bin/console cache:clear
