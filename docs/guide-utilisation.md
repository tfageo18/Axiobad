# Guide d'utilisation — Axiobad

Ce guide explique comment utiliser l'application Axiobad au quotidien. Il est aussi consultable
en ligne, une fois connecté, via le menu du compte (en haut à droite) → **Documentation**.

## Sommaire

- [Se connecter](#se-connecter)
- [Rôles et permissions](#rôles-et-permissions)
- [Gérer les licenciés (bureau)](#gérer-les-licenciés-bureau)
- [Mineurs et responsables légaux](#mineurs-et-responsables-légaux)
- [Importer des licenciés en masse (CSV)](#importer-des-licenciés-en-masse-csv)
- [Saisons et adhésions (bureau)](#saisons-et-adhésions-bureau)
- [Gérer les gymnases (bureau)](#gérer-les-gymnases-bureau)
- [Gérer les équipes (bureau, capitaines)](#gérer-les-équipes-bureau-capitaines)
- [Gérer les créneaux (bureau)](#gérer-les-créneaux-bureau)
- [Le calendrier](#le-calendrier)
- [Indiquer sa présence à un créneau (tous les licenciés)](#indiquer-sa-présence-à-un-créneau-tous-les-licenciés)
- [Gérer le stock (bureau)](#gérer-le-stock-bureau)
- [Historique des présences (bureau, entraîneurs)](#historique-des-présences-bureau-entraîneurs)
- [Cordage (tous les licenciés, cordeurs)](#cordage-tous-les-licenciés-cordeurs)
- [Vie du club / Évènements (tous les licenciés)](#vie-du-club--évènements-tous-les-licenciés)
- [Championnats / Interclubs (tous les licenciés, bureau)](#championnats--interclubs-tous-les-licenciés-bureau)
- [Tableau de bord (bureau)](#tableau-de-bord-bureau)
- [Mon espace (tous les licenciés)](#mon-espace-tous-les-licenciés)
- [Mon profil](#mon-profil)
- [Journal d'audit (bureau)](#journal-daudit-bureau)
- [Mes données personnelles (RGPD)](#mes-données-personnelles-rgpd)
- [Communication ciblée (bureau)](#communication-ciblée-bureau)
- [Notifications automatiques](#notifications-automatiques)
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
| **Entraîneur** | Peut encadrer des créneaux | Peut être sélectionné comme entraîneur sur un créneau encadré ; accède à l'historique des présences |
| **Cordeur** | Gère les demandes de cordage | Voit toutes les demandes de cordage et fait avancer leur statut |
| **Accès au stock** | Accède au module stock | Comme un membre du bureau, mais uniquement pour le stock (les membres du bureau y accèdent déjà) |

Les rôles se gèrent depuis la fiche de création ou de modification d'un licencié (page **Licenciés**,
bureau uniquement).

## Gérer les licenciés (bureau)

Menu **Bureau → Licenciés** (visible uniquement par les membres du bureau).

### Créer un licencié

1. Cliquer sur **+ Nouveau licencié**.
2. Renseigner prénom, nom, email, et cocher éventuellement "Membre du bureau" et/ou "Entraîneur".
3. Valider : un email d'activation est automatiquement envoyé au licencié, avec un lien valable
   7 jours pour qu'il définisse lui-même son mot de passe.

Tant que le licencié n'a pas cliqué sur le lien et défini son mot de passe, son statut apparaît
comme **"En attente d'activation"** dans la liste. Une fois activé, il apparaît comme **"Actif"**.

## Mineurs et responsables légaux

L'email est **optionnel** à la création d'un licencié. Le laisser vide crée un licencié
**sans compte de connexion** — typiquement un enfant mineur — qui n'apparaît jamais dans les
statuts d'activation (il affiche **"Sans compte"**) et ne peut jamais se connecter lui-même.

1. Cliquer sur **+ Nouveau licencié**, renseigner prénom/nom, laisser l'email vide.
2. Dans la section **Mineur et responsables légaux**, choisir un ou deux **responsables légaux**
   parmi les licenciés existants (il faut donc créer le·s parent·s en premier).
3. Compléter les champs utiles à la sécurité de l'enfant : personnes autorisées à venir le
   récupérer, contact d'urgence (nom, téléphone), autorisation de sortie seul(e), droit à l'image.
4. Le champ **allergies / informations de santé** est une donnée sensible : il n'est visible que
   du bureau, des entraîneurs et des responsables légaux (affiché sur la fiche présence du
   licencié). Ne le renseigner que si c'est utile pour la sécurité de l'enfant.

Un responsable légal retrouve tous ses enfants mineurs dans le menu **Ma famille** : leurs
prochains créneaux (avec possibilité de répondre présent/absent en leur nom) et le statut/
paiement de leur adhésion. « Ma famille » est visible de tous les licenciés connectés, même sans
enfant à charge (la liste est alors vide).

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

Menu **Bureau → Licenciés** (ou **Bureau → Adhésions et paiements**, qui pointe vers la même
page) → lien **Gérer les saisons**.

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

Menu **Jouer → Gymnases** (liste visible par tous, actions réservées au bureau).

- **+ Nouveau gymnase** : nom, adresse, téléphone.
- **Clés du gymnase** : depuis la fiche de modification d'un gymnase, ajouter une clé associe un
  licencié porteur et, en option, une **référence** libre (utile s'il existe plusieurs clés pour
  un même gymnase — ex. « Clé principale », « Clé local matériel »). La liste des porteurs de
  clés (avec leur référence) est visible par tous sur la page Gymnases.
- **Activer/désactiver** un gymnase (affichage grisé quand inactif).
- **Supprimer** un gymnase supprime aussi les créneaux qui y sont rattachés.

## Gérer les équipes (bureau, capitaines)

Menu **Club → Équipes**, visible par tous les connectés.

- Le **bureau** crée les équipes (nom, ex. « Equipe 1 R1 », « Equipe vétérans », et une catégorie
  libre), et désigne un **capitaine** parmi les licenciés — seul le bureau peut nommer ou changer
  le capitaine d'une équipe.
- Un licencié peut appartenir à plusieurs équipes.
- Le **capitaine** d'une équipe (même sans rôle bureau) peut lui aussi cliquer sur **Gérer** pour
  modifier le nom/la catégorie de son équipe et ajouter ou retirer des membres — mais il ne peut
  ni supprimer l'équipe, ni changer qui en est le capitaine.
- Le bureau peut **désactiver/réactiver** ou **supprimer** une équipe depuis la liste.

## Gérer les créneaux (bureau)

Menu **Jouer → Créneaux**.

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
9. Optionnel : **capacité maximale** et **heure limite d'annulation** (voir ci-dessous).
10. Valider.

### Rechercher, activer/désactiver, supprimer

- Un champ de **recherche instantanée** filtre la liste des créneaux.
- **Désactiver** un créneau le fait disparaître du calendrier et de la liste vue par les licenciés
  (le bureau continue de le voir, marqué « Inactif »), sans le supprimer.
- **Supprimer** retire définitivement le créneau.

### Capacité et liste d'attente

Si une **capacité maximale** est renseignée sur un créneau :

- Cliquer sur « Je viens » inscrit le licencié directement s'il reste de la place, sinon il est
  placé en **liste d'attente** (premier arrivé, premier servi).
- Quand une personne confirmée se désinscrit, la première personne en liste d'attente est
  **automatiquement promue** : elle a **24h pour confirmer sa place** (bouton dédié, visible sur
  le calendrier, la page du créneau et « Mon espace »). Si elle ne confirme pas à temps, elle
  retourne en fin de liste d'attente et la place est proposée à la personne suivante — ce
  traitement est effectué automatiquement toutes les 10 minutes par une tâche planifiée sur le
  serveur.
- Si un **délai limite d'annulation** est renseigné (en heures avant le début du créneau), un
  licencié confirmé ne peut plus se désinscrire lui-même une fois ce délai dépassé — le bureau,
  lui, peut toujours annuler une inscription.
- Le bureau peut **forcer une inscription** depuis la page détail d'un créneau (bouton en bas de
  page), même si la capacité est déjà atteinte.

### Exceptions ponctuelles sur une occurrence

Pour annuler ou modifier un créneau récurrent **une seule fois**, sans toucher aux autres
occurrences ni au créneau lui-même :

1. Depuis le **calendrier**, sur l'occurrence concernée, cliquer sur **« Annuler / modifier cette
   occurrence »** (visible du bureau uniquement).
2. Choisir **Annuler cette séance**, ou **Modifier exceptionnellement** (gymnase, horaire,
   capacité, entraîneur différents juste pour cette date), et ajouter une note optionnelle
   visible de tous.
3. Une séance annulée n'accepte plus de réponse de présence, et est signalée en rouge partout où
   elle apparaît (calendrier, Mon espace, Ma famille).
4. Depuis la fiche de l'occurrence, un bouton **« Rétablir cette occurrence normalement »** annule
   l'exception et revient au fonctionnement standard du créneau récurrent.

### Fermeture par période (vacances scolaires...)

Depuis la liste des créneaux, lien **Fermeture par période** : indiquer une date de début, une
date de fin et un motif optionnel — toutes les occurrences de tous les créneaux actifs sur cette
période sont annulées d'un coup (chacune peut être rétablie individuellement ensuite si besoin).

## Le calendrier

Menu **Jouer → Calendrier** : vue du mois en cours (mois précédent/suivant navigable).

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

Menu **Bureau → Stock** (réservé au bureau).

- **Vêtements** : type, taille, marque (suggestions de marques sport courantes).
- **Volants** : type, vitesse, destination, marque, modèle (suggestions de marques courantes).
- **Lieu de stockage** (optionnel, texte libre) : où trouver physiquement l'article, affiché dans
  la liste.
- Chaque article a une quantité en stock, gérée via de vrais **mouvements d'entrée/sortie** (avec
  motif optionnel), pas une simple valeur modifiable — un historique de tous les mouvements est
  consultable par article. Une sortie ne peut pas faire passer le stock sous zéro.
- Une **recherche instantanée** filtre les deux catégories à la fois.
- **Seuil d'alerte** (optionnel, en modifiant un article) : quand la quantité descend à ce niveau
  ou en dessous, un badge d'alerte apparaît sur la liste et une alerte est ajoutée au [centre de
  tâches du tableau de bord](#tableau-de-bord-bureau).

### Inventaire physique

Menu **Stock → Inventaire du stock**.

1. **Nouvelle campagne** : fige la quantité théorique de chaque article (vêtements et volants) au
   moment de la création.
2. Pour chaque ligne, saisir la **quantité comptée** lors de l'inventaire physique, et un motif
   optionnel en cas d'écart (utile pour comprendre plus tard).
3. **Valider l'inventaire** : chaque écart constaté (comptée ≠ théorique) régularise
   automatiquement le stock — un vrai mouvement d'entrée ou de sortie est créé (motif « Régularisation
   inventaire »), visible dans l'historique de l'article. Une fois validée, la campagne est figée
   et ne peut plus être modifiée.
4. **Exporter en CSV** à tout moment (théorique, comptée, écart, motif).

## Historique des présences (bureau, entraîneurs)

Pour chaque licencié, son **taux de présence** (nombre de « je viens » ÷ nombre de réponses
données, tous créneaux confondus) est visible directement dans la colonne **Présence** de la
liste des [licenciés](#gérer-les-licenciés-bureau) (bureau). Cliquer dessus (ou sur « Classement
des présences » en haut de la liste) ouvre la fiche détail : récapitulatif, **graphique** de
présence sur les 6 derniers mois glissants, et historique complet des réponses (date, créneau,
présent ou absent).

Les **entraîneurs** qui ne sont pas du bureau n'ont pas accès à la liste des licenciés : ils
retrouvent ce même classement des présences via le menu **Présences**, qui n'apparaît que pour
eux, avec une **recherche instantanée** par nom de licencié.

## Cordage (tous les licenciés, cordeurs)

Menu **Club → Cordage**.

- Chaque licencié peut gérer **ses raquettes** (menu **Cordage → Mes raquettes**) : marque
  (suggestions courantes, texte libre sinon), modèle, un **signe distinctif** (utile s'il existe
  plusieurs raquettes similaires) et une **tension habituelle**. La page **Historique** d'une
  raquette liste toutes les demandes de cordage passées pour cette raquette.
- Tout licencié peut déposer une **demande de cordage** : raquette concernée (optionnel — la
  tension habituelle se pré-remplit automatiquement), cordage souhaité (choisi dans le catalogue
  tenu par le bureau), tension souhaitée, et lieu de dépose. Il ne voit que ses propres demandes,
  et peut annuler une demande tant qu'elle est encore « Déposée ».
- Le **bureau** gère le **catalogue des cordages** proposés (menu **Gérer le catalogue de
  cordages**) : ajout, activation/désactivation, suppression.
- Un licencié peut recevoir le rôle **Cordeur** (coché par le bureau sur sa fiche, comme pour
  Entraîneur). Un cordeur (avec ou sans rôle bureau) voit **toutes** les demandes de cordage,
  peut les **modifier ou annuler** à tout moment (comme le bureau), et fait avancer leur statut :
  1. **Déposée** — demande créée par le licencié.
  2. **En cours** — le cordeur la prend en charge (« Prendre en charge »).
  3. **Prête** — le cordeur indique le lieu de retour et le prix, ce qui marque la raquette prête.
  4. **Récupérée** — le licencié clique sur « J'ai récupéré ma raquette » (le cordeur/bureau peut
     aussi le faire si la remise se fait en direct).

## Vie du club / Évènements (tous les licenciés)

Menu **Club → Vie du club**.

- Le **bureau** crée les événements : type (tournoi interne, barbecue, assemblée générale, stage,
  autre), titre, description, lieu, date/heure de début (et de fin optionnelle), et un nombre de
  places optionnel (laissé vide = illimité).
- Tout licencié peut s'**inscrire** à un événement depuis sa page. Si l'événement est complet, il
  est automatiquement placé en **liste d'attente**.
- Un licencié peut se **désinscrire** à tout moment ; si une place se libère et qu'il y a une
  liste d'attente, la première personne en attente est **automatiquement promue** en participant
  confirmé.
- Le bureau voit et peut retirer n'importe quel participant ou personne en liste d'attente.

## Championnats / Interclubs (tous les licenciés, bureau)

Menu **Club → Interclubs**.

- Le **bureau** crée une **rencontre** pour une équipe existante (voir [Gérer les
  équipes](#gérer-les-équipes-bureau-capitaines)) : numéro de **journée**, date/heure, adversaire,
  lieu, **domicile ou extérieur**, **heure de rendez-vous**, **capitaine de la rencontre**
  (optionnel, sinon celui de l'équipe est affiché), **covoiturage** (texte libre), et peut
  renseigner le **score global** une fois le match joué.
- Sur la page d'une rencontre, le bureau **convoque** les joueurs parmi les membres de l'équipe.
- Chaque joueur convoqué peut indiquer s'il est **présent ou non** à la rencontre, comme pour un
  créneau ; le bureau voit l'état des réponses de toute l'équipe convoquée, et la **composition**
  (joueurs confirmés présents) s'affiche automatiquement.
- **Feuille de rencontre** : le bureau ajoute chaque match individuel — type (**SH, SD, DH, DD,
  MX**), un ou deux joueurs de l'équipe (double), les adversaires (texte libre, pas besoin d'un
  compte), le score détaillé et le résultat (gagné/perdu). Un match peut être supprimé si besoin.
- Menu **Interclubs → Statistiques** : par équipe (rencontres jouées/gagnées/perdues/nulles, basé
  sur le score global des rencontres) et par joueur (matchs joués/gagnés/perdus, basé sur les
  matchs individuels de la feuille de rencontre).
- Chaque rencontre (journée) apparaît aussi dans le [calendrier](#le-calendrier), visible par le
  bureau et par les membres de l'équipe concernée.

## Communication ciblée (bureau)

Menu **Bureau → Communication**.

1. Choisir la **cible** dans le menu déroulant : tous les licenciés, une équipe, les licenciés
   d'un créneau, les non-répondants à la prochaine occurrence d'un créneau, les participants
   confirmés d'un évènement, les adhésions impayées de la saison en cours, les responsables
   légaux, ou par catégorie d'âge (adultes/enfants).
2. Le nombre et la liste des destinataires s'affichent immédiatement — seuls les licenciés ayant
   un compte de connexion actif sont comptés.
3. Saisir le sujet et le message, puis valider.
4. L'envoi et ses résultats (succès, échecs éventuels avec le détail des adresses en échec)
   apparaissent dans l'**historique des envois**, en bas de la page.

## Tableau de bord (bureau)

Menu **Bureau → Tableau de bord**, réservé au bureau.

### Centre de tâches

En haut de la page, une liste d'alertes signale ce qui mérite l'attention du bureau — cliquer
dessus mène directement à la page concernée :
- adhésions impayées ou en attente sur la saison en cours ;
- demandes de suppression de compte à traiter ;
- invitations expirées (licencié jamais activé) ;
- promotions de liste d'attente en attente de confirmation ;
- cordages prêts depuis plus de 7 jours, non récupérés ;
- fiches avec une information de santé renseignée sans consentement valide ;
- licenciés sans compte et sans responsable légal renseigné (mineur mal rattaché) ;
- absence d'ouvreur assigné pour un créneau dans les 2 prochains jours.

Aucune alerte ne s'affiche si rien ne nécessite d'action.

Vue d'ensemble du club en un coup d'œil :
- Nombre de licenciés, répartition hommes/femmes (basée sur le genre renseigné par chacun dans
  son profil, optionnel), répartition adultes/enfants.
- Classements des licenciés (répartition par niveau FFBaD).
- Adhésions payées sur la saison en cours.
- Présences du mois (taux global, tous créneaux confondus) et occupation détaillée de chaque
  créneau sur le mois en cours.
- Volants consommés (sorties de stock) ce mois-ci et au total.
- Valeur du stock, calculée à partir du **prix unitaire** optionnel renseigné sur chaque article
  de vêtement ou tube de volants (page Stock).
- Une **comparaison des saisons** : pour chaque saison configurée, adhésions payées et montant
  total collecté, pour suivre l'évolution d'une saison à l'autre.

## Mon espace (tous les licenciés)

Menu **Mon espace**, votre page personnelle en un coup d'œil :

- Vos **prochains créneaux** (14 jours) parmi ceux qui correspondent à votre catégorie d'âge et
  votre classement, avec votre réponse de présence.
- Vos **statistiques de présence** globales.
- Le statut de votre **adhésion** (payée/en attente/exonérée) et vos paiements sur la saison en
  cours.
- Les **créneaux recommandés** selon votre classement (les mêmes règles que celles utilisées pour
  filtrer le calendrier).
- Les **évènements à venir** du club.
- Votre historique de participation aux **tournois internes** et aux **interclubs**.

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

## Journal d'audit (bureau)

Menu **Bureau → Journal d'audit**.

Trace les actions sensibles effectuées dans l'application : paiement ajouté ou supprimé,
consultation ou modification des informations de santé d'un licencié, changement de responsable
légal, suppression de compte (simple ou forcée), changement de rôle, correction de stock lors
d'un inventaire, annulation de créneau, modification d'adhésion.

Chaque entrée indique la date, l'auteur de l'action, l'objet concerné et, quand c'est pertinent,
l'ancienne et la nouvelle valeur — **jamais le contenu réel des informations de santé**, pour ne
pas dupliquer une donnée sensible dans le journal lui-même. Deux filtres sont disponibles : par
type d'action et par auteur.

## Mes données personnelles (RGPD)

Depuis **Mon compte** :

- **Télécharger mes données** : génère un export au format JSON de toutes les données vous
  concernant (profil, présences, convocations interclubs, inscriptions aux évènements, raquettes,
  demandes de cordage, adhésions et paiements) — droit à la portabilité.
- **Demander la suppression de mon compte** : transmet une demande de suppression au bureau, qui
  la traite (badge visible sur votre fiche dans la liste des licenciés) via le bouton **« Forcer
  la suppression »**, qui efface le compte et toutes les données qui lui sont liées.

Depuis **Ma famille**, un responsable légal peut aussi **télécharger les données de chacun de ses
enfants** (même export JSON).

La saisie d'une information de santé (allergies) sur la fiche d'un licencié ou d'un mineur exige
de cocher la case de **consentement explicite** — sans cette case, l'enregistrement est refusé.
Tout octroi ou retrait de ce consentement est tracé dans le [journal d'audit](#journal-daudit-bureau).

Les liens **Politique de confidentialité**, **Mentions légales** et **Registre des traitements**,
en pied de page de chaque écran, détaillent les données collectées et vos droits.

### Anonymisation (bureau)

À la différence de la suppression, **anonymiser** un compte (bouton disponible sur un compte
**désactivé**, page Licenciés) efface son identité et ses données personnelles tout en
**conservant ses données comptables** (adhésions, paiements) pour les obligations légales. Un
compte désactivé depuis plus de **3 ans** est anonymisé **automatiquement** (tâche planifiée
mensuelle) — cette durée correspond à la politique de conservation du club, détaillée dans le
[registre des traitements](/registre-traitements).

## Notifications automatiques

Axiobad envoie des emails automatiquement, sans action du bureau :

- **Immédiatement** : quand une raquette est marquée prête (cordage), et quand une place se
  libère sur un créneau à capacité limitée (promotion depuis la liste d'attente, à confirmer sous
  24h).
- **Une fois par jour** (8h du matin) : rappel de réponse à un créneau dans 2 jours si vous n'avez
  pas encore répondu, rappel si votre promotion de liste d'attente expire bientôt, rappel si votre
  invitation de compte expire bientôt, rappel d'un évènement auquel vous êtes inscrit dans 2 jours,
  rappel d'une convocation interclubs dans 3 jours si vous n'avez pas répondu, et — chaque lundi —
  rappel des adhésions impayées de la saison en cours.

Ces notifications sont **désactivées par défaut** pour tout nouveau licencié. Pour les activer,
cocher **« Recevoir les notifications automatiques par email »** sur la page **Mon compte**. Le
**bureau peut aussi changer ce réglage** pour n'importe quel licencié depuis sa fiche (page
Licenciés → Modifier). Cela n'affecte pas l'email d'activation de compte (essentiel), ni les
communications ciblées envoyées manuellement par le bureau (voir ci-dessus) — seulement ces
rappels automatiques.

Il n'y a pas encore d'historique des notifications reçues, ni de réglage plus fin (choisir quels
types de rappels recevoir).

### Notifications push sur un appareil

En complément de l'email, chaque licencié peut activer les **notifications push navigateur** sur
un ou plusieurs de ses appareils (téléphone, ordinateur), depuis la page **Mon compte** → section
« Notifications push sur cet appareil » → bouton **« Activer les notifications push sur cet
appareil »**. Le navigateur demande alors l'autorisation d'afficher des notifications ; une fois
accordée, cet appareil reçoit désormais les mêmes évènements que ceux envoyés par email (raquette
prête, rappels quotidiens...), même quand Axiobad n'est pas ouvert. Le bouton se transforme en
**« Désactiver »** pour couper les notifications sur cet appareil précis — les autres appareils
éventuellement abonnés ne sont pas affectés.

Cette activation est **par appareil et par navigateur**, indépendante de la préférence email
ci-dessus : un licencié peut par exemple ne recevoir que du push sur son téléphone et couper les
emails, ou l'inverse. Si le serveur n'a pas de clé VAPID configurée, la section l'indique et le
push reste simplement indisponible (voir la section [Mise en place](README.md#mise-en-place) du
README pour l'activer en production).

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
