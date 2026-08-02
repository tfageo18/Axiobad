# Guide d'utilisation — Axiobad

Ce guide explique comment utiliser l'application Axiobad au quotidien. Il est aussi consultable
en ligne, une fois connecté, via le menu du compte (en haut à droite) → **Documentation**.

## Sommaire

- [Se connecter](#se-connecter)
- [Rôles et permissions](#rôles-et-permissions)
- [Gérer les licenciés (bureau)](#gérer-les-licenciés-bureau)
- [Importer des licenciés en masse (CSV)](#importer-des-licenciés-en-masse-csv)
- [Saisons et adhésions (bureau)](#saisons-et-adhésions-bureau)
- [Gérer les gymnases (bureau)](#gérer-les-gymnases-bureau)
- [Gérer les équipes (bureau, capitaines)](#gérer-les-équipes-bureau-capitaines)
- [Gérer les créneaux (bureau)](#gérer-les-créneaux-bureau)
- [Le calendrier](#le-calendrier)
- [Indiquer sa présence à un créneau (tous les licenciés)](#indiquer-sa-présence-à-un-créneau-tous-les-licenciés)
- [Gérer le stock (bureau)](#gérer-le-stock-bureau)
- [Historique des présences (bureau, entraîneurs)](#historique-des-présences-bureau-entraîneurs)
- [Mon profil](#mon-profil)
- [Configuration email (production)](#configuration-email-production)

## Se connecter

Rendez-vous sur l'URL du club (ex. `https://axiobad.thomas-fageol.fr`) et connectez-vous avec
votre email et votre mot de passe. Le menu de navigation n'est visible qu'une fois connecté ; la
page d'accueil (et la connexion) redirigent vers le **calendrier**.

**Premier compte administrateur du club :**
- Email : `admin@axiobad.local`
- Mot de passe : `admin`

⚠️ Ce mot de passe doit impérativement être changé : l'application vous redirige automatiquement
vers la page de changement de mot de passe dès la première connexion, et bloque l'accès au reste
de l'application tant que ce n'est pas fait. Ce compte administrateur par défaut est protégé :
il ne peut être ni désactivé ni supprimé, et n'est pas soumis au suivi des adhésions.

## Rôles et permissions

Chaque licencié peut cumuler plusieurs rôles :

| Rôle | Description | Accès |
|---|---|---|
| **Licencié** | Rôle de base, attribué automatiquement à tout le monde | Consulter le calendrier et les créneaux, indiquer sa présence, gérer son profil |
| **Membre du bureau** | Rôle administrateur | Tout ce que peut faire un licencié + gestion des licenciés, saisons, gymnases, créneaux et stock |
| **Entraîneur** | Peut encadrer des créneaux | Peut être sélectionné comme entraîneur sur un créneau encadré |

Les rôles se gèrent depuis la fiche de création ou de modification d'un licencié (page **Licenciés**,
bureau uniquement).

## Gérer les licenciés (bureau)

Menu **Licenciés** (visible uniquement par les membres du bureau).

### Créer un licencié

1. Cliquer sur **+ Nouveau licencié**.
2. Renseigner prénom, nom, email, et cocher éventuellement "Membre du bureau" et/ou "Entraîneur".
3. Valider : un email d'activation est automatiquement envoyé au licencié, avec un lien valable
   7 jours pour qu'il définisse lui-même son mot de passe.

Tant que le licencié n'a pas cliqué sur le lien et défini son mot de passe, son statut apparaît
comme **"En attente d'activation"** dans la liste. Une fois activé, il apparaît comme **"Actif"**.

### Rechercher, trier, désactiver, supprimer

- Un champ de **recherche instantanée** filtre la liste par nom, email ou rôle.
- Cliquer sur l'en-tête d'une colonne pour trier la liste selon cette colonne.
- **Désactiver** un compte bloque sa connexion sans supprimer ses données (présences, historique...).
  Le compte peut être réactivé à tout moment.
- **Supprimer** retire définitivement le licencié ; si des données lui sont liées (présences,
  créneaux encadrés, mouvements de stock...), la suppression est refusée — désactiver le compte à
  la place.
- Le compte administrateur par défaut n'affiche pas ces actions : il est protégé.

### Modifier le classement d'un licencié

Il n'existe pas d'API publique fiable de la Fédération Française de Badminton pour récupérer
automatiquement un classement (testé : ni `api.ffbad.org`, ni badiste.fr, ni myffbad.fr n'offrent
un accès exploitable). Le classement simple/double/mixte se saisit donc manuellement — par le
bureau depuis la fiche du licencié, ou par le licencié lui-même depuis son profil — sur l'échelle
officielle FFBaD (NC, P12, P11, P10, D9, D8, D7, R6, R5, R4, N3, N2, N1, du plus faible au plus
fort), en la consultant sur [myffbad.fr](https://myffbad.fr/recherche/joueur).

## Importer des licenciés en masse (CSV)

Depuis la page **Licenciés**, lien **Importer en masse (CSV)** :

1. Télécharger le modèle CSV (bouton dédié) : colonnes `prenom`, `nom`, `email`, `bureau`,
   `entraineur`, séparateur point-virgule.
2. Compléter une ligne par licencié à créer. Pour les colonnes `bureau` et `entraineur`, indiquer
   `oui` ou `non`.
3. Importer le fichier complété. Un email d'activation est envoyé automatiquement à chaque nouveau
   licencié créé, comme pour une création individuelle. Les lignes avec un email déjà utilisé ou
   invalide sont ignorées et listées après l'import (rien n'est perdu, seules ces lignes ne sont
   pas importées).

## Saisons et adhésions (bureau)

Menu **Licenciés** → lien **Gérer les saisons**.

- Créer une saison avec un libellé, une date de début et une date de fin. Une saison est
  « en cours » si la date du jour est comprise entre ces deux dates.
- Depuis la liste des licenciés, sélectionner une saison affiche le statut d'adhésion de chacun ;
  cliquer dessus ouvre la fiche d'adhésion du licencié pour cette saison :
  - **Statut** : payée, en attente, ou exonérée.
  - **Montant dû** (optionnel).
  - **Versements** : un ou plusieurs paiements (montant, date, moyen — CB, chèque, espèces,
    virement — et numéro de chèque le cas échéant), pour gérer un paiement en plusieurs fois.
    Le **montant restant** se calcule automatiquement (montant dû − somme des versements), et le
    statut passe automatiquement à « Payée » une fois le montant dû intégralement couvert.
- Un résumé indique le nombre d'impayés pour la saison sélectionnée, avec un filtre rapide
  **Voir uniquement les impayés**. Le compte administrateur par défaut est exclu de ce suivi
  (« Non applicable »), n'étant pas un licencié soumis à l'adhésion.

## Gérer les gymnases (bureau)

Menu **Gymnases** (liste visible par tous, actions réservées au bureau).

- **+ Nouveau gymnase** : nom, adresse, téléphone.
- **Clés du gymnase** : depuis la fiche de modification d'un gymnase, ajouter une clé associe un
  licencié porteur et, en option, une **référence** libre (utile s'il existe plusieurs clés pour
  un même gymnase — ex. « Clé principale », « Clé local matériel »). La liste des porteurs de
  clés (avec leur référence) est visible par tous sur la page Gymnases.
- **Activer/désactiver** un gymnase (affichage grisé quand inactif).
- **Supprimer** un gymnase supprime aussi les créneaux qui y sont rattachés.

## Gérer les équipes (bureau, capitaines)

Menu **Équipes**, visible par tous les connectés.

- Le **bureau** crée les équipes (nom, ex. « Equipe 1 R1 », « Equipe vétérans », et une catégorie
  libre), et désigne un **capitaine** parmi les licenciés — seul le bureau peut nommer ou changer
  le capitaine d'une équipe.
- Un licencié peut appartenir à plusieurs équipes.
- Le **capitaine** d'une équipe (même sans rôle bureau) peut lui aussi cliquer sur **Gérer** pour
  modifier le nom/la catégorie de son équipe et ajouter ou retirer des membres — mais il ne peut
  ni supprimer l'équipe, ni changer qui en est le capitaine.

## Gérer les créneaux (bureau)

Menu **Créneaux**.

### Créer un créneau

1. Cliquer sur **+ Nouveau créneau**.
2. Renseigner le nom et l'**activité** (Badminton par défaut ; Footing et Musculation suggérés,
   mais texte libre pour toute autre activité).
3. Choisir le gymnase, le jour de la semaine, l'heure de début et de fin.
4. Optionnel : dates de **répétition** (début/fin) si le créneau doit être borné dans le temps —
   des boutons **Effacer** à côté de chaque champ permettent de vider la date facilement.
5. Choisir la **catégorie** (Adultes ou Enfants).
6. Cocher **Loisir** et/ou **Compétiteur** (cumulables), et éventuellement **Ouvert aux personnes
   extérieures au club** et/ou **Ouvert aux ados** — informations affichées partout où le créneau
   apparaît (liste, détail, calendrier).
7. Si besoin, un **classement minimum requis** (échelle FFBaD) pour filtrer les licenciés d'un
   certain niveau (laisser vide si aucun niveau minimum).
8. Cocher **Créneau encadré** si un entraîneur doit être associé, puis le sélectionner dans la
   liste (seuls les licenciés ayant le rôle Entraîneur apparaissent).
9. Valider.

### Rechercher, activer/désactiver, supprimer

- Un champ de **recherche instantanée** filtre la liste des créneaux.
- **Désactiver** un créneau le fait disparaître du calendrier et de la liste vue par les licenciés
  (le bureau continue de le voir, marqué « Inactif »), sans le supprimer.
- **Supprimer** retire définitivement le créneau.

## Le calendrier

Menu **Calendrier** : vue du mois en cours (mois précédent/suivant navigable).

- Sur ordinateur : grille mensuelle classique.
- Sur mobile : vue agenda, jour par jour, qui **masque les jours déjà passés** (le premier jour
  affiché est toujours aujourd'hui).
- Cliquer sur un créneau affiche son détail : qui vient, qui ne vient pas, qui n'a pas encore
  répondu.
- Pour chaque créneau et chaque date, le bureau peut indiquer qui **ouvre** et qui **ferme** le
  gymnase ; cette information est visible par tous.

## Indiquer sa présence à un créneau (tous les licenciés)

Par défaut, chaque licencié voit uniquement les créneaux qui correspondent à **sa catégorie d'âge**
(déduite de sa date de naissance) et à **son niveau** (si un classement minimum est requis). Un
lien **Voir tous les créneaux** permet d'afficher l'ensemble des créneaux du club. Les membres du
bureau voient toujours tous les créneaux (y compris désactivés).

Sur chaque créneau (liste ou calendrier), deux boutons : **Je viens** / **Je ne viens pas**. Le
choix est enregistré instantanément, semaine par semaine, et peut être changé à tout moment avant
le créneau.

## Gérer le stock (bureau)

Menu **Stock** (réservé au bureau).

- **Vêtements** : type, taille, marque (suggestions de marques sport courantes).
- **Volants** : type, vitesse, destination, marque, modèle (suggestions de marques courantes).
- Chaque article a une quantité en stock, gérée via de vrais **mouvements d'entrée/sortie** (avec
  motif optionnel), pas une simple valeur modifiable — un historique de tous les mouvements est
  consultable par article. Une sortie ne peut pas faire passer le stock sous zéro.
- Une **recherche instantanée** filtre les deux catégories à la fois.

## Historique des présences (bureau, entraîneurs)

Menu **Présences** (réservé au bureau et aux entraîneurs).

- La liste affiche, pour chaque licencié, son **taux de présence** (nombre de « je viens » ÷
  nombre de réponses données, tous créneaux confondus), son nombre de séances où il/elle était
  présent·e, et le nombre total de réponses données. La liste est triée par taux décroissant.
- La fiche **détail** d'un licencié affiche un récapitulatif, un **graphique** de présence sur les
  6 derniers mois glissants, et l'historique complet de ses réponses (date, créneau, présent ou
  absent).
- Cet outil est utile pour les entraîneurs afin de suivre l'assiduité des joueurs.

## Mon profil

Chaque licencié peut compléter et modifier lui-même son profil en cliquant sur son nom en haut à
droite → **Modifier mon profil** :
- **Photo de profil** (affichée en rond).
- **Email**, **téléphone**.
- **Date de naissance** : sert à déterminer automatiquement la catégorie d'âge (moins de 18 ans =
  Enfant, 18 ans et plus = Adulte), utilisée pour filtrer les créneaux adaptés.
- **Numéro de licence FFBaD**.
- **Classement** simple/double/mixte (voir [Modifier le classement d'un
  licencié](#modifier-le-classement-dun-licencié)).

Le menu du compte propose aussi un accès direct à la **documentation** et à la **déconnexion**.

## Configuration email (production)

Pour que les emails d'invitation partent réellement en production, la variable d'environnement
`MAILER_DSN` doit pointer vers un vrai service d'envoi SMTP dans `.env.prod.local` sur le serveur,
par exemple avec **Amazon SES** (utilisé en production pour Axiobad, 62 000 emails/mois gratuits
depuis une instance EC2) :

```
MAILER_DSN=smtp://IDENTIFIANT:MOT_DE_PASSE@email-smtp.RÉGION.amazonaws.com:587
MAILER_FROM=noreply@votre-domaine.fr
```

Un compte SES neuf démarre en mode **sandbox** (envoi limité aux adresses vérifiées manuellement) :
il faut demander la sortie du sandbox (« production access ») dans la console SES pour pouvoir
envoyer à n'importe quelle adresse de licencié. Le domaine d'envoi doit aussi être vérifié (DKIM).

En développement local (sans serveur mail configuré), l'email n'est pas réellement envoyé
(DSN `null://null`) — c'est le comportement par défaut si `MAILER_DSN` n'est pas renseigné.

Puis redémarrer les conteneurs (`docker compose -f compose.yaml -f compose.prod.yaml
--env-file .env.prod.local up -d`).
