#!/bin/bash
# Script "user data" EC2 (Amazon Linux 2023) pour déployer Axiobad sur une seule instance,
# la moins chère possible (ex: t4g.nano / t4g.micro), base de données PostgreSQL dans un conteneur
# avec ses données persistées sur le volume EBS de l'instance, HTTPS via Let's Encrypt.
#
# Deux sites sont servis par la même instance, le même conteneur nginx et la même base
# PostgreSQL (bases distinctes) — pas besoin de monter en gamme l'instance :
# - hac.axiobad.click  : production réelle.
# - demo.axiobad.click : instance de démonstration publique, données 100% fictives,
#                        réinitialisées chaque nuit (voir compose.demo.yaml).
set -euo pipefail

REPO_URL="https://github.com/tfageo18/Axiobad.git"
BRANCH="claude/readme-initial-9yd1ej"
APP_DIR="/opt/axiobad"
DOMAIN="hac.axiobad.click"
DEMO_DOMAIN="demo.axiobad.click"
LETSENCRYPT_EMAIL="contact@axioweb.fr"
COMPOSE="/usr/bin/docker compose -f compose.yaml -f compose.prod.yaml -f compose.demo.yaml --env-file .env.prod.local --env-file .env.demo.local"

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
MAILER_FROM=no-reply@axiobad.click
EOF
    chmod 600 .env.prod.local
fi

# Secrets de l'instance de démo : base et clé d'appli séparées de la prod, DEMO_MODE forcé côté
# compose.demo.yaml (pas besoin de le répéter ici).
if [ ! -f .env.demo.local ]; then
    cat > .env.demo.local <<EOF
DEMO_APP_SECRET=$(openssl rand -hex 32)
DEMO_POSTGRES_DB=app_demo
EOF
    chmod 600 .env.demo.local
fi

mkdir -p /var/www/certbot

# Obtention des certificats initiaux : nginx n'est pas encore démarré, on utilise le mode
# standalone (certbot ouvre lui-même un mini-serveur sur le port 80 le temps de la validation
# ACME). On retente plusieurs fois : le DNS peut ne pas encore être propagé partout juste après
# le boot (vrai aussi pour demo.axiobad.click si son enregistrement DNS vient d'être créé).
for d in "$DOMAIN" "$DEMO_DOMAIN"; do
    if [ ! -d "/etc/letsencrypt/live/$d" ]; then
        for attempt in $(seq 1 10); do
            if /usr/local/bin/certbot certonly --standalone --non-interactive --agree-tos \
                --email "$LETSENCRYPT_EMAIL" -d "$d"; then
                break
            fi
            echo "Tentative $attempt pour $d échouée, nouvel essai dans 30s..."
            sleep 30
        done
    fi
done

$COMPOSE up -d --build

# La base "app_demo" n'est pas créée automatiquement par l'image Postgres (initdb ne tourne
# qu'au tout premier démarrage du volume, déjà utilisé par la base de prod) : on la crée nous-
# mêmes si besoin, avant de lancer les migrations dessus.
DEMO_DB_EXISTS=$($COMPOSE exec -T database psql -U app -tAc "SELECT 1 FROM pg_database WHERE datname='app_demo'")
if [ "$DEMO_DB_EXISTS" != "1" ]; then
    $COMPOSE exec -T database psql -U app -c "CREATE DATABASE app_demo"
fi

$COMPOSE exec -T -u www-data php bin/console doctrine:migrations:migrate --no-interaction
$COMPOSE exec -T -u www-data php-demo bin/console doctrine:migrations:migrate --no-interaction
# Premier jeu de données de démo (les fois suivantes, c'est le cron nocturne qui s'en charge).
$COMPOSE exec -T -u www-data php-demo bin/console app:demo:reset --no-interaction
# Filet de sécurité : si un exec précédent (root) a laissé des fichiers de cache mal owned, on corrige.
$COMPOSE exec -T -u root php chown -R www-data:www-data /var/www/html/var
$COMPOSE exec -T -u root php-demo chown -R www-data:www-data /var/www/html/var

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
ExecStart=$COMPOSE up -d
ExecStop=$COMPOSE down
TimeoutStartSec=300

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable axiobad.service

# Renouvellement automatique : une fois nginx démarré, on utilise le mode webroot
# (pas besoin de couper le service), puis on recharge la conf nginx. Couvre les deux domaines
# (hac.axiobad.click et demo.axiobad.click) en une seule commande.
cat > /etc/cron.d/certbot-renew <<EOF
0 3 * * * root /usr/local/bin/certbot renew --webroot -w /var/www/certbot --quiet --deploy-hook "cd $APP_DIR && $COMPOSE exec -T nginx nginx -s reload"
EOF

# Promotions de créneau expirées (liste d'attente) : traitées toutes les 10 minutes.
cat > /etc/cron.d/axiobad-expirer-promotions <<EOF
*/10 * * * * root cd $APP_DIR && $COMPOSE exec -T php bin/console app:creneau:expirer-promotions >> /var/log/axiobad-expirer-promotions.log 2>&1
EOF

# Notifications récapitulatives quotidiennes (rappels créneau, adhésions impayées...) : 8h du matin.
cat > /etc/cron.d/axiobad-notifications-quotidiennes <<EOF
0 8 * * * root cd $APP_DIR && $COMPOSE exec -T php bin/console app:notifications:quotidiennes >> /var/log/axiobad-notifications-quotidiennes.log 2>&1
EOF

# RGPD : anonymisation des comptes désactivés depuis plus de 3 ans (durée de conservation) —
# le 1er de chaque mois à 4h.
cat > /etc/cron.d/axiobad-rgpd-purge <<EOF
0 4 1 * * root cd $APP_DIR && $COMPOSE exec -T php bin/console app:rgpd:purger-comptes-inactifs >> /var/log/axiobad-rgpd-purge.log 2>&1
EOF

# Sauvegarde de la base vers S3 (pg_dump compressé), tous les jours à 2h. Le bucket purge
# automatiquement les sauvegardes de plus de 30 jours (règle de cycle de vie S3).
chmod +x "$APP_DIR/deploy/aws/backup-database.sh"
cat > /etc/cron.d/axiobad-backup-db <<EOF
0 2 * * * root $APP_DIR/deploy/aws/backup-database.sh >> /var/log/axiobad-backup-db.log 2>&1
EOF

# Communications ciblées programmées (envoi différé) : envoyées dès que leur date/heure arrive,
# vérifié toutes les 5 minutes.
cat > /etc/cron.d/axiobad-communications-planifiees <<EOF
*/5 * * * * root cd $APP_DIR && $COMPOSE exec -T php bin/console app:communication:envoyer-planifiees >> /var/log/axiobad-communications-planifiees.log 2>&1
EOF

# Instance de démo : réinitialisation complète (données jetables, potentiellement modifiées par
# des visiteurs) chaque nuit à 5h. app:demo:reset refuse de s'exécuter si DEMO_MODE n'est pas
# activé sur le conteneur ciblé, donc sans risque pour la base de prod même en cas d'erreur de
# copier-coller de cette ligne.
cat > /etc/cron.d/axiobad-demo-reset <<EOF
0 5 * * * root cd $APP_DIR && $COMPOSE exec -T -u www-data php-demo bin/console app:demo:reset --no-interaction >> /var/log/axiobad-demo-reset.log 2>&1
EOF
