# Axiobad

Application de gestion de club de badminton, développée en **Symfony** + **Twig**, exécutée dans **Docker**.

## Objectif

Fournir aux clubs de badminton un outil pour gérer leurs licenciés, leurs adhésions, leurs créneaux
(badminton, mais aussi d'autres activités type footing ou musculation), leurs gymnases, leurs
équipes et interclubs, leur vie associative (évènements), leur cordage, leur stock (vêtements,
volants), et donner à chaque licencié une page personnelle ainsi qu'un tableau de bord au bureau.

## Documentation

- 📘 [Guide d'utilisation](docs/guide-utilisation.md) — utilisation au quotidien (aussi consultable
  en ligne, une fois connecté, via le menu du compte → **Documentation**).

## Modules

### Gestion des licenciés

- Chaque licencié dispose d'un compte (nom, prénom, email, téléphone, genre optionnel, photo, date
  de naissance, numéro de licence FFBaD, classement simple/double/mixte).
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
- Le lien d'activation (pour définir son mot de passe) peut être **renvoyé** par le bureau si le
  licencié ne l'a pas reçu ou s'il a expiré (valable 7 jours).
- Rôles cumulables :
  - **Licencié** : rôle de base, tout adhérent du club.
  - **Membre du bureau** : rôle administrateur de l'application.
  - **Entraîneur** : peut être associé aux créneaux encadrés, accède à l'historique des présences.
  - **Cordeur** : gère les demandes de cordage du club.
  - **Accès au stock** : accède au module stock sans être membre du bureau (les membres du bureau
    y ont accès automatiquement).
- Menu masqué tant que l'utilisateur n'est pas connecté.

### Mineurs et responsables légaux

- L'email est **optionnel** à la création d'un licencié : le laisser vide crée un licencié **sans
  compte de connexion** (typiquement un enfant mineur), rattaché à un ou deux **responsables
  légaux** choisis parmi les licenciés existants — ça évite de créer une adresse email
  artificielle pour un enfant.
- Pour un mineur : personnes autorisées à venir le récupérer, contact d'urgence (nom, téléphone),
  autorisation de sortie seul(e), droit à l'image, et des informations de santé/allergies
  (donnée sensible, visible uniquement du bureau, des entraîneurs et des responsables légaux —
  affichée sur la fiche présence).
- Chaque licencié voit dans **« Ma famille »** (menu) les fiches des mineurs dont il est
  responsable légal : prochains créneaux (avec réponse présence en son nom), et statut/paiement
  de leur adhésion.
- Le bouton « Renvoyer l'invitation » n'apparaît que pour les licenciés ayant un compte.

### Saisons et adhésions

- Le bureau définit des saisons (libellé, date de début, date de fin).
- Pour chaque saison et chaque licencié, l'adhésion a un **statut** (payée / en attente /
  exonérée), un montant total, et peut être réglée en **plusieurs paiements** (date, moyen —
  CB, chèque avec numéro, espèces, virement — et montant), avec calcul automatique du montant
  restant dû. Un résumé et un filtre rapide « voir uniquement les impayés » sont disponibles.
  Le compte administrateur par défaut n'est pas soumis à l'adhésion.

### Historique des présences

- Pour chaque licencié : taux de présence, nombre de séances, et un graphique de présence sur les
  6 derniers mois glissants. Visible directement dans la fiche/liste des licenciés pour le bureau ;
  les entraîneurs qui ne sont pas du bureau y accèdent via un menu dédié, avec recherche instantanée.

### Équipes

- Le bureau crée des équipes (nom libre, ex. « Equipe 1 R1 », « Equipe vétérans »), avec
  **championnat** et **division** optionnels, et désigne un **capitaine** parmi les licenciés. Un
  licencié peut appartenir à plusieurs équipes.
- Le capitaine (même sans être du bureau) peut gérer le nom/la catégorie de son équipe et ses
  membres, mais pas supprimer l'équipe ni changer le capitaine.
- Le bureau peut **désactiver/réactiver** ou **supprimer** une équipe.

### Championnats / Interclubs

- Le bureau crée des rencontres pour une équipe : numéro de **journée**, date, adversaire, lieu,
  **domicile ou extérieur**, **heure de rendez-vous**, **capitaine de la rencontre** (par défaut
  celui de l'équipe), **covoiturage** (texte libre), et le score global une fois jouée.
- Les joueurs de l'équipe sont **convoqués** et peuvent indiquer s'ils sont présents ou non à la
  rencontre, comme pour un créneau. La **composition** (joueurs confirmés présents) est affichée
  sur la page de la rencontre.
- **Feuille de rencontre** : le bureau ajoute chaque match individuel (SH, SD, DH, DD, MX) avec
  les joueurs de l'équipe engagés, les adversaires (texte libre), le score détaillé et le résultat.
- **Statistiques** (menu dédié) : par équipe (rencontres jouées/gagnées/perdues/nulles à partir du
  score global) et par joueur (matchs joués/gagnés/perdus à partir des matchs individuels).
- Chaque rencontre apparaît aussi dans le calendrier, visible par le bureau et les membres de
  l'équipe concernée.

### Vie du club / Évènements

- Le bureau crée des évènements (tournoi interne, barbecue, assemblée générale, stage, autre) avec
  un nombre de places optionnel.
- Tout licencié peut s'inscrire ; si l'évènement est complet, il passe en **liste d'attente**,
  promue automatiquement dès qu'une place se libère.

### Cordage

- Chaque licencié gère ses **raquettes** (marque, modèle, signe distinctif, tension habituelle en
  kg) et consulte l'historique de cordage de chacune.
- Tout licencié peut déposer une demande de cordage : raquette concernée (la tension habituelle
  se pré-remplit), cordage souhaité (choisi dans un catalogue tenu par le bureau), tension
  souhaitée, lieu de dépose.
- Un licencié avec le rôle **Cordeur** (avec ou sans rôle bureau) voit toutes les demandes, peut
  les modifier ou annuler à tout moment, et fait progresser leur statut : déposée → en cours →
  prête (avec prix et lieu de retour) → récupérée (le licencié peut lui-même marquer sa raquette
  récupérée).

### Tableau de bord (bureau)

- **Centre de tâches** : liste d'alertes actionnables en haut de page (cliquables vers la page
  concernée) — adhésions impayées, demandes de suppression de compte à traiter, invitations
  expirées, promotions de liste d'attente en attente de confirmation, cordages prêts depuis plus
  de 7 jours, informations de santé sans consentement valide, licenciés sans compte et sans
  responsable légal, absence d'ouvreur assigné dans les 2 prochains jours.
- Vue d'ensemble du club : nombre de licenciés, répartition hommes/femmes, adultes/enfants,
  répartition des classements, adhésions payées sur la saison en cours, taux de présence du mois
  et occupation détaillée de chaque créneau, volants consommés, valeur du stock (à partir d'un
  prix unitaire optionnel sur chaque article), et une **comparaison des saisons** (adhésions
  payées, montant collecté).

### Mon espace

- Chaque licencié dispose d'une page personnelle : ses prochains créneaux (calendrier
  personnalisé), ses statistiques de présence, le statut de son adhésion, des créneaux
  recommandés selon son classement, les évènements à venir, et son historique de participation
  aux tournois internes et aux interclubs.

### Gymnases

- Nom, adresse, téléphone, nombre de terrains.
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
  club** (indicatif, affiché partout où le créneau apparaît), **ouvert aux ados** (à partir de
  11 ans) — un créneau adultes marqué « ouvert aux ados » apparaît aussi comme un créneau adapté
  pour un licencié mineur d'au moins 11 ans, y compris dans « Ma famille » pour son responsable
  légal.
- Dates de **répétition** (début/fin) optionnelles.
- Peut être activé/désactivé (un créneau désactivé disparaît du calendrier et de la liste pour
  les licenciés).
- Recherche instantanée sur la liste des créneaux.
- **Capacité maximale** optionnelle avec **liste d'attente** : promotion automatique en cas de
  désistement (délai de confirmation de 24h pour la personne promue, sans quoi elle repasse en
  fin de liste et la place est proposée au suivant), **heure limite d'annulation** optionnelle
  au-delà de laquelle un licencié ne peut plus se désinscrire lui-même, et possibilité pour le
  bureau de **forcer une inscription**. Un traitement planifié côté serveur (cron, toutes les
  10 min, `app:creneau:expirer-promotions`) gère les promotions expirées.
- **Exceptions ponctuelles** (bureau) : pour une occurrence précise (une date donnée), possibilité
  d'**annuler** la séance ou de la **modifier exceptionnellement** (gymnase, horaire, capacité,
  entraîneur, note) sans toucher au créneau récurrent ni à ses autres occurrences. Depuis le
  calendrier ou en **fermeture groupée sur une période** (ex. vacances scolaires), qui annule en
  une fois toutes les occurrences concernées.

### Calendrier

- Vue mensuelle (grille sur ordinateur, agenda journalier sur mobile — qui masque les jours déjà
  passés).
- Chaque licencié voit par défaut les créneaux adaptés à son âge et son niveau (bascule possible
  vers « tous les créneaux »). Les membres du bureau voient toujours tout.
- Présence/absence indiquée directement depuis le calendrier, semaine par semaine.
- Ouverture/fermeture du gymnase (qui ouvre, qui ferme) gérée par créneau et par date, visible par
  tous.
- Cliquer sur un créneau affiche le détail (qui vient, qui ne vient pas, qui n'a pas répondu), avec
  un export **.ics** (agenda téléphone/ordinateur) et un lien direct **Google Agenda**.

### Stock (mini-WMS)

- Réservé au rôle **Accès au stock** (ou au bureau).
- Vêtements (type, taille, marque) et volants (type, vitesse, destination, marque, modèle),
  avec les marques/modèles courants suggérés, et un **lieu de stockage** optionnel (texte libre,
  ex. « Local matériel, armoire 2 »).
- Vrais mouvements de stock (entrées/sorties tracées avec motif et historique), pas une simple
  quantité éditable.
- **Seuil d'alerte** optionnel par article : badge d'alerte sur la liste du stock (et alerte sur
  le tableau de bord) quand la quantité descend à ce niveau ou en dessous.
- **Inventaire physique** : une campagne fige la quantité théorique de chaque article ; le bureau
  saisit la quantité comptée pour chacun, puis **valide** — ce qui **régularise automatiquement le
  stock** (mouvement d'entrée/sortie tracé pour chaque écart) et fige la campagne. **Export CSV**
  de chaque campagne (théorique, comptée, écart, motif).
- Recherche instantanée sur les deux catégories.

### Progressive Web App (PWA)

- Application **installable** sur l'écran d'accueil (mobile et desktop) : manifest avec icônes,
  couleur de thème, mode plein écran (`standalone`).
- **Service worker** : cache les assets statiques (icônes, manifest) pour un chargement plus
  rapide, et affiche une page dédiée en cas de perte de connexion. Les pages dynamiques (créneaux,
  présences, adhésions...) ne sont jamais mises en cache, pour garantir des données toujours à
  jour et ne jamais servir un jeton CSRF périmé.
- **Notifications push navigateur** (Web Push) : chaque licencié peut activer les notifications
  push sur un appareil depuis « Mon compte » (indépendamment de la préférence email). Repose sur
  des clés VAPID générées côté serveur (`app:push:generer-cles-vapid`) ; sans clé configurée, la
  fonctionnalité est simplement absente (pas d'erreur).

### Communication ciblée

- Menu **Bureau → Communication** : envoi d'un email libre (sujet + message) à un groupe de
  licenciés ayant un compte actif — tous les licenciés, une équipe, les licenciés d'un créneau,
  les non-répondants à la prochaine occurrence d'un créneau, les participants confirmés d'un
  évènement, les adhésions impayées de la saison en cours, les responsables légaux, ou par
  catégorie d'âge (adultes/enfants).
- **Aperçu des destinataires** (nombre et noms) avant envoi.
- **Historique** des envois (sujet, cible, nombre de destinataires, échecs détaillés, auteur,
  date), consultable sur la même page.
- Pas encore de modèles réutilisables, de programmation différée, ni de pièces jointes.

### Notifications automatiques

- **Emails immédiats** pour les évènements importants : raquette prête à récupérer (cordage),
  promotion depuis une liste d'attente de créneau (à confirmer sous 24h).
- **Récapitulatif quotidien** par email (commande `app:notifications:quotidiennes`, cron 8h) :
  rappel de réponse à un créneau (J-2 sans réponse), rappel de promotion bientôt expirée,
  invitation de compte bientôt expirée, évènement à venir (J-2, inscrits confirmés), convocation
  interclubs à venir sans réponse (J-3), et — chaque lundi — rappel des adhésions impayées de la
  saison en cours.
- **Désactivées par défaut** (opt-in) pour tout nouveau licencié. Chaque licencié peut les activer
  lui-même depuis « Mon compte » (case à cocher), et le **bureau peut aussi changer ce réglage**
  pour n'importe quel licencié depuis sa fiche. N'affecte ni les emails essentiels (activation de
  compte), ni les communications ciblées envoyées manuellement par le bureau.
- Chaque notification automatique (et communication ciblée) est aussi envoyée en **notification
  push navigateur** aux appareils sur lesquels le destinataire l'a activée (voir PWA ci-dessous).
  Pas encore d'historique des notifications envoyées.

### Journal d'audit

- Menu **Bureau → Journal d'audit** : trace les actions sensibles — paiement ajouté/supprimé,
  consultation ou modification des informations de santé, changement de responsable légal,
  suppression de compte (simple ou forcée), changement de rôle, correction de stock (inventaire),
  annulation de créneau, modification d'adhésion.
- Chaque entrée indique l'auteur, la date, l'objet concerné, et l'ancienne/nouvelle valeur quand
  c'est pertinent (jamais le contenu des données de santé elles-mêmes, pour ne pas dupliquer une
  donnée sensible). Filtrable par type d'action et par auteur.

### RGPD

- Pages publiques **Politique de confidentialité**, **Mentions légales** et **Registre des
  traitements** (liens en pied de page) : données collectées par catégorie, finalités, base
  légale, destinataires, durée de conservation.
- La saisie d'une **information de santé** (allergies) exige de cocher un **consentement
  explicite** (RGPD art. 9) ; sans consentement, l'enregistrement est refusé. Tout octroi ou
  **retrait de consentement** est tracé dans le journal d'audit (historique des consentements).
- Chaque licencié peut, depuis **« Mon compte »** :
  - **télécharger l'ensemble de ses données** (droit à la portabilité, export JSON : profil,
    présences, convocations, inscriptions, raquettes, demandes de cordage, adhésions et
    paiements) ;
  - **demander la suppression de son compte** (droit à l'effacement). La demande est signalée au
    bureau (badge dans la liste des licenciés), qui la traite via **« Forcer la suppression »**.
- Un responsable légal peut **télécharger les données de chacun de ses enfants** depuis « Ma
  famille ».
- **Anonymisation** (distincte de la suppression) : le bureau peut anonymiser un compte
  **désactivé** — son identité et ses données personnelles sont effacées, mais les données
  comptables liées (adhésions, paiements) sont conservées pour les obligations légales. Une
  commande planifiée (`app:rgpd:purger-comptes-inactifs`, mensuelle) anonymise automatiquement
  les comptes désactivés depuis plus de **3 ans** (durée de conservation).
- Toute consultation ou modification des données de santé, changement de responsable légal,
  suppression/anonymisation de compte est tracée dans le [journal d'audit](#journal-daudit).

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
3. Générer une paire de clés VAPID (`php bin/console app:push:generer-cles-vapid` dans le
   conteneur `app`) et renseigner `VAPID_PUBLIC_KEY`/`VAPID_PRIVATE_KEY`/`VAPID_SUBJECT` dans
   `.env.prod.local` pour activer les notifications push navigateur (sinon la fonctionnalité est
   simplement absente, sans erreur).

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

## État actuel et pistes d'évolution

Tous les modules listés dans [Modules](#modules) sont en production. Ce qui n'a **pas** encore
été construit, à considérer pour la suite :

- **Vue calendrier hebdomadaire dédiée** : la vue mensuelle (desktop) et l'agenda journalier
  (mobile) couvrent le besoin aujourd'hui — à évaluer si une vue semaine apporterait vraiment
  quelque chose de plus.
- **Sauvegarde automatisée** de la base (snapshot EBS ou `pg_dump` planifié) : documentée
  ci-dessus mais pas encore mise en place concrètement.
- Les notifications n'ont pas encore d'historique consultable (le réglage marche/arrêt existe).
- Pas d'**application mobile native** — le site est une PWA installable (icône, plein écran, cache
  hors-ligne des assets), pas une app iOS/Android compilée.
