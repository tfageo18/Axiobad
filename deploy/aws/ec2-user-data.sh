#!/bin/bash
# Script "user data" EC2 (Amazon Linux 2023) pour déployer Axiobad sur une seule instance,
# la moins chère possible (ex: t4g.nano / t4g.micro), base de données PostgreSQL dans un conteneur
# avec ses données persistées sur le volume EBS de l'instance, HTTPS via Let's Encrypt.
set -euo pipefail

REPO_URL="https://github.com/tfageo18/Axiobad.git"
BRANCH="claude/readme-initial-9yd1ej"
APP_DIR="/opt/axiobad"
DOMAIN="axiobad.thomas-fageol.fr"
LETSENCRYPT_EMAIL="thomas.fageol@gmail.com"

dnf update -y
dnf install -y docker git python3-pip cronie
systemctl enable --now crond

# certbot n'est pas packagé pour AL2023/arm64 via dnf, on l'installe via pip.
pip3 install --quiet certbot

systemctl enable --now docker

mkdir -p /usr/local/lib/docker/cli-plugins
curl -SL "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-$(uname -m)" \
    -o /usr/local/lib/docker/cli-plugins/docker-compose
chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

# "docker compose build" nécessite buildx, non fourni par le paquet docker d'AL2023.
BUILDX_ARCH="amd64"
[ "$(uname -m)" = "aarch64" ] && BUILDX_ARCH="arm64"
BUILDX_VERSION=$(curl -sL https://api.github.com/repos/docker/buildx/releases/latest | grep -oP '"tag_name": "\K(.*)(?=")')
curl -SL "https://github.com/docker/buildx/releases/download/${BUILDX_VERSION}/buildx-${BUILDX_VERSION}.linux-${BUILDX_ARCH}" \
    -o /usr/local/lib/docker/cli-plugins/docker-buildx
chmod +x /usr/local/lib/docker/cli-plugins/docker-buildx

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
MAILER_DSN=null://null
MAILER_FROM=noreply@${DOMAIN}
EOF
    chmod 600 .env.prod.local
fi

mkdir -p /var/www/certbot

# Obtention du certificat initial : nginx n'est pas encore démarré, on utilise le mode standalone
# (certbot ouvre lui-même un mini-serveur sur le port 80 le temps de la validation ACME).
# On retente plusieurs fois : le DNS peut ne pas encore être propagé partout juste après le boot.
if [ ! -d "/etc/letsencrypt/live/$DOMAIN" ]; then
    for attempt in $(seq 1 10); do
        if /usr/local/bin/certbot certonly --standalone --non-interactive --agree-tos \
            --email "$LETSENCRYPT_EMAIL" -d "$DOMAIN"; then
            break
        fi
        echo "Tentative $attempt échouée, nouvel essai dans 30s..."
        sleep 30
    done
fi

docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local up -d --build
docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local exec -T -u www-data php bin/console doctrine:migrations:migrate --no-interaction
# Filet de sécurité : si un exec précédent (root) a laissé des fichiers de cache mal owned, on corrige.
docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local exec -T -u root php chown -R www-data:www-data /var/www/html/var

# Démarrage garanti au boot (démarrage EC2, pas seulement redémarrage à chaud) : on ne compte pas
# uniquement sur "restart: unless-stopped" des conteneurs, qui peut ne pas se redéclencher de façon
# fiable après un arrêt/démarrage complet de l'instance (pas un simple reboot).
cat > /etc/systemd/system/axiobad.service <<EOF
[Unit]
Description=Axiobad docker compose stack
Requires=docker.service
After=docker.service network-online.target
Wants=network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local up -d
ExecStop=/usr/bin/docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local down
TimeoutStartSec=300

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable axiobad.service

# Renouvellement automatique : une fois nginx démarré, on utilise le mode webroot
# (pas besoin de couper le service), puis on recharge la conf nginx.
cat > /etc/cron.d/certbot-renew <<EOF
0 3 * * * root /usr/local/bin/certbot renew --webroot -w /var/www/certbot --quiet --deploy-hook "cd $APP_DIR && docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local exec -T nginx nginx -s reload"
EOF

# Promotions de créneau expirées (liste d'attente) : traitées toutes les 10 minutes.
cat > /etc/cron.d/axiobad-expirer-promotions <<EOF
*/10 * * * * root cd $APP_DIR && docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local exec -T php bin/console app:creneau:expirer-promotions >> /var/log/axiobad-expirer-promotions.log 2>&1
EOF
