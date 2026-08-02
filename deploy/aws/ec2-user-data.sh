#!/bin/bash
# Script "user data" EC2 (Amazon Linux 2023) pour déployer Axiobad sur une seule instance,
# la moins chère possible (ex: t4g.nano / t4g.micro), base de données PostgreSQL dans un conteneur
# avec ses données persistées sur le volume EBS de l'instance.
#
# Pré-requis avant de lancer l'instance :
# - avoir créé le fichier .env.prod.local (à partir de .env.prod.example) et le déposer
#   manuellement sur le serveur après le premier boot (ne JAMAIS le mettre dans ce script ni dans le repo).
# - avoir un Security Group qui autorise le port 22 (SSH, restreint à ton IP) et le port 80/443 (HTTP/HTTPS).
set -euo pipefail

REPO_URL="https://github.com/tfageo18/Axiobad.git"
APP_DIR="/opt/axiobad"

dnf update -y
dnf install -y docker git

systemctl enable --now docker

# Installe le plugin docker compose (v2)
mkdir -p /usr/local/lib/docker/cli-plugins
curl -SL https://github.com/docker/compose/releases/latest/download/docker-compose-linux-$(uname -m) \
    -o /usr/local/lib/docker/cli-plugins/docker-compose
chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

if [ ! -d "$APP_DIR" ]; then
    git clone "$REPO_URL" "$APP_DIR"
fi

cd "$APP_DIR"

echo "Déploiement initial: déposer .env.prod.local dans $APP_DIR puis lancer :"
echo "  docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local up -d --build"
echo "  docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local exec php bin/console doctrine:migrations:migrate --no-interaction"
