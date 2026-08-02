#!/bin/bash
# Script "user data" EC2 (Amazon Linux 2023) pour déployer Axiobad sur une seule instance,
# la moins chère possible (ex: t4g.nano / t4g.micro), base de données PostgreSQL dans un conteneur
# avec ses données persistées sur le volume EBS de l'instance.
set -euo pipefail

REPO_URL="https://github.com/tfageo18/Axiobad.git"
BRANCH="claude/readme-initial-9yd1ej"
APP_DIR="/opt/axiobad"

dnf update -y
dnf install -y docker git

systemctl enable --now docker

mkdir -p /usr/local/lib/docker/cli-plugins
curl -SL "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-$(uname -m)" \
    -o /usr/local/lib/docker/cli-plugins/docker-compose
chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

if [ ! -d "$APP_DIR" ]; then
    git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
fi

cd "$APP_DIR"

# Génère les secrets de prod au premier boot si absents (persistés sur l'EBS de l'instance).
if [ ! -f .env.prod.local ]; then
    cat > .env.prod.local <<EOF
APP_SECRET=$(openssl rand -hex 32)
POSTGRES_USER=app
POSTGRES_PASSWORD=$(openssl rand -hex 24)
POSTGRES_DB=app
POSTGRES_VERSION=16
FFBAD_API_BASE_URL=https://api.ffbad.org
EOF
    chmod 600 .env.prod.local
fi

docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local up -d --build
docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local exec -T php bin/console doctrine:migrations:migrate --no-interaction
