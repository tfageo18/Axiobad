# Guide d'utilisation — Axiobad

Ce guide explique comment utiliser l'application Axiobad au quotidien : connexion, gestion des
licenciés, des gymnases et des créneaux.

## Sommaire

- [Se connecter](#se-connecter)
- [Rôles et permissions](#rôles-et-permissions)
- [Gérer les licenciés (bureau)](#gérer-les-licenciés-bureau)
- [Gérer les gymnases (bureau)](#gérer-les-gymnases-bureau)
- [Gérer les créneaux (bureau)](#gérer-les-créneaux-bureau)
- [Le calendrier](#le-calendrier)
- [Indiquer sa présence à un créneau (tous les licenciés)](#indiquer-sa-présence-à-un-créneau-tous-les-licenciés)
- [Consulter et rafraîchir son classement](#consulter-et-rafraîchir-son-classement)
- [Mon profil (photo, licence, date de naissance)](#mon-profil-photo-licence-date-de-naissance)

## Se connecter

Rendez-vous sur l'URL du club (ex. `https://axiobad.thomas-fageol.fr`) et connectez-vous avec
votre email et votre mot de passe.

**Premier compte administrateur du club :**
- Email : `admin@axiobad.local`
- Mot de passe : `admin`

⚠️ Ce mot de passe doit impérativement être changé : l'application vous redirige automatiquement
vers la page de changement de mot de passe dès la première connexion, et bloque l'accès au reste
de l'application tant que ce n'est pas fait.

## Rôles et permissions

Chaque licencié peut cumuler plusieurs rôles :

| Rôle | Description | Accès |
|---|---|---|
| **Licencié** | Rôle de base, attribué automatiquement à tout le monde | Consulter les créneaux, indiquer sa présence |
| **Membre du bureau** | Rôle administrateur | Tout ce que peut faire un licencié + gestion des licenciés, gymnases et créneaux |
| **Entraîneur** | Peut encadrer des créneaux | Peut être sélectionné comme entraîneur sur un créneau encadré |

Les rôles se gèrent depuis la fiche de création d'un licencié (voir ci-dessous) ; il n'y a pas
encore d'interface pour modifier les rôles d'un licencié existant (à faire directement via un
membre du bureau technique si besoin).

## Gérer les licenciés (bureau)

Menu **Licenciés** (visible uniquement par les membres du bureau).

### Créer un licencié

1. Cliquer sur **+ Nouveau licencié**.
2. Renseigner prénom, nom, email, et cocher éventuellement "Membre du bureau" et/ou "Entraîneur".
3. Valider : un email d'activation est automatiquement envoyé au licencié, avec un lien valable
   7 jours pour qu'il définisse lui-même son mot de passe.

Tant que le licencié n'a pas cliqué sur le lien et défini son mot de passe, son statut apparaît
comme **"En attente d'activation"** dans la liste. Une fois activé, il apparaît comme **"Actif"**.

> En développement local (sans serveur mail configuré), l'email n'est pas réellement envoyé
> (DSN `null://null`). En production, la variable d'environnement `MAILER_DSN` doit pointer vers
> un vrai service d'envoi d'emails (SMTP, Mailgun, etc.) pour que les invitations partent
> réellement — voir la section [Configuration email](#configuration-email-production).

### Rafraîchir le classement d'un licencié

Sur la ligne du licencié, bouton **Rafraîchir le classement** : récupère le classement simple,
double et mixte du licencié via l'API de la Fédération Française de Badminton, à partir de son
numéro de licence.

## Gérer les gymnases (bureau)

Menu **Gymnases**.

- **+ Nouveau gymnase** : renseigner le nom et l'adresse.
- **Supprimer** : retire le gymnase (attention, les créneaux qui y sont rattachés sont supprimés
  avec lui).

## Gérer les créneaux (bureau)

Menu **Créneaux**.

### Créer un créneau

1. Cliquer sur **+ Nouveau créneau**.
2. Renseigner le nom, le gymnase, le jour de la semaine, l'heure de début et de fin.
3. Optionnel : dates de **répétition** (début/fin) si le créneau doit être borné dans le temps
   (ex. saison sportive) — laisser vide pour un créneau sans date de fin.
4. Choisir la **catégorie** (Adultes ou Enfants) et, si besoin, un **classement minimum requis**
   pour filtrer les licenciés d'un certain niveau (laisser vide si aucun niveau minimum).
5. Cocher **Créneau encadré** si un entraîneur doit être associé, puis le sélectionner dans la
   liste (seuls les licenciés ayant le rôle Entraîneur apparaissent).
6. Valider.

### Supprimer un créneau

Bouton **Supprimer** sur la ligne du créneau concerné.

## Le calendrier

Menu **Calendrier** : vue hebdomadaire de tous les créneaux, organisés par jour, avec horaire,
gymnase, catégorie, niveau minimum et encadrement.

## Indiquer sa présence à un créneau (tous les licenciés)

Sur la page **Créneaux**, chaque licencié voit par défaut uniquement les créneaux qui correspondent
à **sa catégorie d'âge** (déduite de sa date de naissance) et à **son niveau** (si un classement
minimum est requis sur le créneau). Un lien **Voir tous les créneaux** permet d'afficher l'ensemble
des créneaux du club, y compris ceux qui ne correspondent pas à son profil. Les membres du bureau
voient toujours tous les créneaux.

Sur chaque créneau, deux boutons : **Je viens** / **Je ne viens pas**. Le choix est enregistré
instantanément et peut être changé à tout moment avant le créneau.

## Consulter et rafraîchir son classement

Le classement (simple / double / mixte) est visible par le bureau sur la fiche du licencié
(page **Licenciés**). Sa mise à jour se fait manuellement via le bouton **Rafraîchir le
classement**, qui interroge l'API de la FFBaD.

## Mon profil (photo, licence, date de naissance)

Chaque licencié peut compléter et modifier lui-même son profil en cliquant sur son nom en haut à
droite (page **Mon compte**) :
- **Photo de profil** (affichée en rond).
- **Téléphone**.
- **Date de naissance** : sert à déterminer automatiquement la catégorie d'âge (moins de 18 ans =
  Enfant, 18 ans et plus = Adulte), utilisée pour filtrer les créneaux adaptés.
- **Numéro de licence FFBaD** : nécessaire pour que le bureau puisse rafraîchir le classement du
  licencié via l'API de la fédération.

## Configuration email (production)

Pour que les emails d'invitation partent réellement en production, éditer `.env.prod.local` sur
le serveur et renseigner :

```
MAILER_DSN=smtp://user:motdepasse@smtp.fournisseur.com:587
MAILER_FROM=noreply@votre-domaine.fr
```

Puis redémarrer les conteneurs (`docker compose -f compose.yaml -f compose.prod.yaml
--env-file .env.prod.local up -d`).
