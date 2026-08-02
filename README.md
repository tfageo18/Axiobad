# Axiobad

Application de gestion de club de badminton, développée en **Symfony** + **Twig**, exécutée dans **Docker**.

## Objectif

Fournir aux clubs de badminton un outil pour gérer leurs licenciés, leurs créneaux d'entraînement et leurs gymnases.

## Modules

### Module 1 — Gestion des licenciés

- Chaque licencié dispose d'un compte et d'informations personnelles classiques (nom, prénom, email, coordonnées, etc.).
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

_À compléter au fur et à mesure de la mise en place du projet._
