# Axiobad

Application de gestion de club de badminton, développée en **Symfony** + **Twig**, exécutée dans **Docker**.

## Objectif

Fournir aux clubs de badminton un outil pour gérer leurs licenciés, leurs adhésions, leurs créneaux
(badminton, mais aussi d'autres activités type footing ou musculation), leurs gymnases et leur stock
(vêtements, volants).

## Documentation

- 📘 [Guide d'utilisation](docs/guide-utilisation.md) — utilisation au quotidien (aussi consultable
  en ligne, une fois connecté, via le menu du compte → **Documentation**).

## Modules

### Gestion des licenciés

- Chaque licencié dispose d'un compte (nom, prénom, email, téléphone, photo, date de naissance,
  numéro de licence FFBaD, classement simple/double/mixte).
- Un compte administrateur par défaut est créé automatiquement (`admin@axiobad.local` / `admin`),
  avec changement de mot de passe obligatoire à la première connexion. Ce compte est protégé :
  il ne peut pas être désactivé ni supprimé.
- Le bureau peut créer des licenciés un par un, ou **en masse via un import CSV** (modèle
  téléchargeable) : un email d'invitation est envoyé à chacun pour définir son mot de passe
  (lien valable 7 jours).
- Chaque licencié peut gérer son propre profil (page "Mon compte" via le menu du compte en haut à
  droite) : photo, email, téléphone, date de naissance, numéro de licence, classement.
- Classement saisi manuellement (simple/double/mixte), sur l'échelle officielle FFBaD (NC à N1) —
  il n'existe pas d'API publique fiable pour le récupérer automatiquement.
- La liste des licenciés propose une recherche instantanée, un tri par colonne, la désactivation/
  réactivation d'un compte (bloque la connexion sans supprimer les données), et la suppression.
- Rôles cumulables :
  - **Licencié** : rôle de base, tout adhérent du club.
  - **Membre du bureau** : rôle administrateur de l'application.
  - **Entraîneur** : peut être associé aux créneaux encadrés.
- Menu masqué tant que l'utilisateur n'est pas connecté.

### Saisons et adhésions

- Le bureau définit des saisons (libellé, date de début, date de fin).
- Pour chaque saison, le statut de paiement de l'adhésion de chaque licencié est suivi (payée /
  non payée), avec un résumé et un filtre rapide « voir uniquement les impayés ». Le compte
  administrateur par défaut n'est pas soumis à l'adhésion.

### Gymnases

- Nom, adresse, téléphone.
- **Porteurs de clés** : chaque clé associée à un gymnase a un porteur (licencié) et une référence
  libre (utile s'il existe plusieurs clés pour un même gymnase — « clé principale », « clé local
  matériel »...), visible par tous.
- Un gymnase peut être activé/désactivé.

### Créneaux

- Chaque créneau est rattaché à un gymnase, a un nom, une **activité** (Badminton par défaut,
  Footing/Musculation suggérés, texte libre sinon), un jour de la semaine, un horaire.
- Peut être **encadré** (associé à un entraîneur) ou libre.
- **Catégorie** (adultes ou enfants) et **classement minimum requis** (échelle FFBaD), utilisés
  pour filtrer les créneaux adaptés à chaque licencié.
- **Type** : loisir et/ou compétiteur (cases cumulables), **ouvert aux personnes extérieures au
  club**, **ouvert aux ados** — indicatifs, affichés partout où le créneau apparaît.
- Dates de **répétition** (début/fin) optionnelles.
- Peut être activé/désactivé (un créneau désactivé disparaît du calendrier et de la liste pour
  les licenciés).
- Recherche instantanée sur la liste des créneaux.

### Calendrier

- Vue mensuelle (grille sur ordinateur, agenda journalier sur mobile — qui masque les jours déjà
  passés).
- Chaque licencié voit par défaut les créneaux adaptés à son âge et son niveau (bascule possible
  vers « tous les créneaux »). Les membres du bureau voient toujours tout.
- Présence/absence indiquée directement depuis le calendrier, semaine par semaine.
- Ouverture/fermeture du gymnase (qui ouvre, qui ferme) gérée par créneau et par date, visible par
  tous.
- Cliquer sur un créneau affiche le détail (qui vient, qui ne vient pas, qui n'a pas répondu).

### Stock (mini-WMS)

- Réservé au bureau.
- Vêtements (type, taille, marque) et volants (type, vitesse, destination, marque, modèle),
  avec les marques/modèles courants suggérés.
- Vrais mouvements de stock (entrées/sorties tracées avec motif et historique), pas une simple
  quantité éditable.
- Recherche instantanée sur les deux catégories.

## Stack technique

- [Symfony](https://symfony.com/) 8.1 (PHP 8.4)
- [Twig](https://twig.symfony.com/)
- [Doctrine](https://www.doctrine-project.org/) ORM + Migrations
- [league/commonmark](https://commonmark.thephpleague.com/) pour le rendu de la documentation
- [PostgreSQL](https://www.postgresql.org/) 16
- [Docker](https://www.docker.com/) / Docker Compose
- Envoi d'emails via [Amazon SES](https://aws.amazon.com/ses/) en production (SMTP)

## Démarrage en local

```bash
docker compose build
docker compose up -d
docker compose exec php bin/console doctrine:database:create
docker compose exec php bin/console doctrine:migrations:migrate
```

L'application est accessible sur http://localhost:8080.

## Environnement de production

L'application est déployée sur **https://axiobad.thomas-fageol.fr** :
- HTTPS via Let's Encrypt (renouvellement automatique par cron).
- Hébergée sur une seule instance EC2 (Elastic IP, DNS Route53).
- Démarrage garanti au boot de l'instance via un service systemd (`axiobad.service`) qui relance
  la stack Docker, en plus du `restart: unless-stopped` des conteneurs.
- Emails transactionnels envoyés via Amazon SES (domaine vérifié avec DKIM).

## Déploiement AWS (option la moins chère)

La base de données PostgreSQL tourne dans un conteneur (pas de RDS), sur une **seule instance EC2**
(architecture ARM Graviton). C'est l'option la moins chère pour un usage 24/7 léger : quelques
euros par mois (instance + volume EBS), contre un coût bien plus élevé avec ECS Fargate ou RDS.

### Mise en place

1. Créer une instance EC2 (Amazon Linux 2023, ARM Graviton), avec :
   - un Security Group ouvrant le port 22 (SSH, restreint), 80 (HTTP) et 443 (HTTPS) ;
   - le script `deploy/aws/ec2-user-data.sh` en "user data" au lancement (installe Docker, clone
     le dépôt dans `/opt/axiobad`, obtient le certificat Let's Encrypt, démarre l'application, et
     installe le service systemd `axiobad.service` pour un démarrage garanti au boot).
2. Configurer `.env.prod.local` sur le serveur (généré automatiquement au premier boot avec des
   secrets aléatoires ; éditer `MAILER_DSN`/`MAILER_FROM` pour un vrai envoi d'emails — voir la
   section [Configuration email](docs/guide-utilisation.md#configuration-email-production) du
   guide).

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
