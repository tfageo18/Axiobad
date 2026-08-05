#!/bin/bash
# Sauvegarde quotidienne de la base PostgreSQL vers S3 (pg_dump compressé).
# Lancé par cron sur l'instance EC2 (voir /etc/cron.d/axiobad-backup-db, installé par
# ec2-user-data.sh). Le bucket S3 a une règle de cycle de vie qui purge automatiquement les
# sauvegardes de plus de 30 jours, donc pas de nettoyage à faire ici côté S3.
set -euo pipefail

APP_DIR="/opt/axiobad"
BUCKET="axiobad-backups-555004096876"
DATE="$(date +%Y-%m-%d_%H%M%S)"
FICHIER="/tmp/axiobad-db-${DATE}.sql.gz"

cd "$APP_DIR"

# Le nom d'utilisateur/base est lu depuis les variables d'environnement du conteneur lui-même
# (définies par compose.yaml), pas depuis le shell hôte qui ne les connaît pas forcément.
docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local exec -T database \
    sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip > "$FICHIER"

aws s3 cp "$FICHIER" "s3://${BUCKET}/pg-dumps/${DATE}.sql.gz" --only-show-errors

rm -f "$FICHIER"
