# Axiobad

Application de gestion de club de badminton, développée en **Symfony** + **Twig**, exécutée dans **Docker**.

## Objectif

Fournir aux clubs de badminton un outil pour gérer leurs licenciés, leurs créneaux d'entraînement et leurs gymnases.

## Documentation

- 📘 [Guide d'utilisation](docs/guide-utilisation.md) — connexion, gestion des licenciés, des gymnases et des créneaux au quotidien.

## Modules

### Module 1 — Gestion des licenciés

- Chaque licencié dispose d'un compte et d'informations personnelles classiques (nom, prénom, email, coordonnées, etc.).
- Un compte administrateur par défaut est créé automatiquement (`admin@axiobad.local` / `admin`), avec changement de mot de passe obligatoire à la première connexion.
- Le bureau peut créer de nouveaux licenciés depuis l'application : un email d'invitation leur est envoyé pour qu'ils définissent eux-mêmes leur mot de passe (lien valable 7 jours).
- Récupération du classement du licencié via l'API de la Fédération Française de Badminton (FFBaD).
- Rôles (cumulables, un licencié peut appartenir à plusieurs groupes à la fois) :
  - **Licencié** : rôle de base, tout adhérent du club.
  - **Membre du bureau** : rôle administrateur de l'application.
  - **Entraîneur** : peut être associé aux créneaux encadrés.

### Module 2 — Gestion des créneaux et des gymnases

- Gestion des gymnases (lieux de pratique).
- Création, modification et suppression des créneaux d'entraînement.
- Chaque créneau est rattaché à un gymnase.
- Un créneau peut être **encadré** (associé à un entraîneur) ou **libre**.
- Les licenciés peuvent indiquer leur présence ou absence sur chaque créneau.

## Stack technique

- [Symfony](https://symfony.com/)
- [Twig](https://twig.symfony.com/)
- [Docker](https://www.docker.com/) / Docker Compose

## Démarrage

```bash
docker compose build
docker compose up -d
docker compose exec php bin/console doctrine:database:create
docker compose exec php bin/console doctrine:migrations:migrate
```

L'application est accessible sur http://localhost:8080.

## Environnement de production

L'application est déployée sur **https://axiobad.thomas-fageol.fr** (HTTPS via Let's Encrypt, EC2 + Elastic IP).

## Déploiement AWS (option la moins chère)

La base de données PostgreSQL tourne dans un conteneur (pas de RDS), sur une **seule instance EC2**
(`t4g.nano` ou `t4g.micro`, architecture ARM Graviton). C'est l'option la moins chère pour un usage
24/7 léger : quelques euros par mois (instance + volume EBS de 8-10 Go), contre un coût bien plus
élevé avec ECS Fargate ou RDS.

### Mise en place

1. Créer une instance EC2 (Amazon Linux 2023, `t4g.nano`/`t4g.micro`), avec :
   - un Security Group ouvrant le port 22 (SSH, restreint à ton IP) et le port 80 (HTTP, `0.0.0.0/0`) ;
   - le script `deploy/aws/ec2-user-data.sh` en "user data" au lancement (installe Docker et clone le dépôt dans `/opt/axiobad`).
2. Une fois l'instance démarrée, s'y connecter en SSH et déposer les secrets de prod :
   ```bash
   cd /opt/axiobad
   cp .env.prod.example .env.prod.local
   # éditer .env.prod.local avec de vrais secrets (APP_SECRET, POSTGRES_PASSWORD, ...)
   ```
3. Démarrer l'application :
   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local up -d --build
   docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local exec php bin/console doctrine:migrations:migrate --no-interaction
   ```

Le fichier `compose.prod.yaml` est un overlay du `compose.yaml` de dev : pas de bind-mount du code,
`APP_ENV=prod`, redémarrage automatique des conteneurs, port PostgreSQL non exposé publiquement.

### Mises à jour

Après un `git push`, sur le serveur :

```bash
bash deploy/aws/redeploy.sh
```

### Sauvegarde

La base de données étant dans un conteneur avec un volume Docker (donc sur l'EBS de l'instance),
il n'y a pas de sauvegarde automatique gérée par AWS. Il est recommandé de planifier :
- un snapshot EBS régulier de l'instance (via AWS Backup, quelques centimes par Go/mois), et/ou
- un `pg_dump` régulier (cron) vers un bucket S3 (stockage très peu coûteux).
