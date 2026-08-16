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
- 📖 [Wiki du projet](../../wiki) — fonctionnalités détaillées, déploiement, notifications, RGPD.

## Licence et contributions

Projet non open source (voir [LICENSE](LICENSE)) — développé et maintenu par Axioweb.
Les **issues** et **pull requests** sont les bienvenues, voir [CONTRIBUTING.md](CONTRIBUTING.md).

## Interface

- **Menu latéral** regroupé par section (Jouer / Club / Bureau), repliable en icônes seules
  (bouton en haut de la zone de contenu) sur ordinateur, transformé en tiroir sur mobile.
- **Mode clair / sombre / système**, au choix via le sélecteur en haut à droite — préférence
  mémorisée par navigateur. **Sombre par défaut** tant qu'aucun choix explicite n'a été fait.
- Menu caché tant que l'utilisateur n'est pas connecté (page de connexion épurée, sans navigation).

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
- **Mot de passe** : au moins 8 caractères avec une majuscule, une minuscule et un chiffre, et
  rejeté s'il apparaît dans une base de fuites de données connues (vérification via l'API [Have I
  Been Pwned](https://haveibeenpwned.com/), interrogée en k-anonymity — seul un préfixe de hash
  est transmis, jamais le mot de passe). Vérifié à l'activation du compte et à chaque changement,
  puis **revérifié périodiquement à la connexion** (au plus 1×/semaine par compte) pour rattraper
  un service externe injoignable au moment de la création, ou une fuite découverte après coup — un
  bandeau d'avertissement s'affiche alors, sans bloquer l'accès à l'application.
- Chaque licencié peut gérer son propre profil (page "Mon compte" via le menu du compte en haut à
  droite) : photo, email, téléphone, date de naissance, numéro de licence, classement, et son
  **équipe interclub par défaut** (parmi ses équipes) si membre de plusieurs — modifiable aussi par
  le bureau depuis la fiche du licencié.
- Classement (simple/double/mixte) et numéro de licence saisis manuellement, sur l'échelle
  officielle FFBaD (NC à N1) — ou récupérés automatiquement via la
  [synchronisation MyFFBaD](#synchronisation-myffbad).
- La liste des licenciés propose une recherche instantanée, un tri par colonne, la désactivation/
  réactivation d'un compte (bloque la connexion sans supprimer les données), et la suppression.
- Le lien d'activation (pour définir son mot de passe) peut être **renvoyé** par le bureau si le
  licencié ne l'a pas reçu ou s'il a expiré (valable 7 jours).
- **Mot de passe oublié** : lien en self-service sur la page de connexion (email → lien de
  réinitialisation valable 7 jours, message générique pour ne pas révéler les comptes existants).
  Le bureau peut aussi déclencher cet envoi depuis la fiche d'un licencié.
- Rôles cumulables :
  - **Licencié** : rôle de base, tout adhérent du club.
  - **Membre du bureau** : rôle administrateur de l'application.
  - **Entraîneur** : peut être associé aux créneaux encadrés, accède à l'historique des présences.
  - **Cordeur** : gère les demandes de cordage du club.
  - **Accès au stock** : accède au module stock sans être membre du bureau (les membres du bureau
    y ont accès automatiquement).
- Menu masqué tant que l'utilisateur n'est pas connecté.

### Synchronisation MyFFBaD

Le numéro de licence, le genre et les classements (simple/double/mixte) d'un licencié peuvent
être synchronisés automatiquement depuis [MyFFBaD](https://myffbad.fr) plutôt que saisis à la
main :
- MyFFBaD n'a pas d'API publique documentée, mais sa page de recherche joueur (Next.js) embarque
  les résultats en JSON directement dans le HTML rendu côté serveur — récupérés sans
  authentification ni exécution de JavaScript (`App\Ffbad\MyFfbadClient`).
- Recherche individuelle (fiche d'un licencié, ou "Mon compte" pour le licencié lui-même) ou
  synchronisation groupée de tout le club (liste des licenciés, bureau) — matching par nom/prénom
  normalisé (accents/casse ignorés).
- Statut (réussie / aucune correspondance / jamais tentée) et date de la dernière tentative
  affichés sur la fiche du licencié et sur "Mon compte".
- La **catégorie d'âge FFBaD** (ex. "Minime 2", "Senior") récupérée à cette occasion s'affiche à
  titre informatif (avec un badge "mineur selon MyFFBaD" quand la catégorie l'indique), **et** met
  à jour un champ dédié `Catégorie d'âge` sur la fiche du licencié — une liste déroulante fermée
  (`App\Badminton\CategorieAge::CODES` : Mini-Bad, Poussin 1/2, Benjamin 1/2, Minime 1/2, Cadet 1/2,
  Junior 1/2, Senior, Vétéran 1 à 7), modifiable manuellement par le bureau à tout moment. C'est
  **indicatif uniquement** : MyFFBaD ne fournissant pas de date de naissance exacte, ce champ
  n'écrase jamais `dateNaissance` ni le statut légal de minorité (`Licencie::estMineur()`, seule
  source utilisée pour les responsables légaux et le consentement santé).
- **Import depuis MyFFBaD** (menu Licenciés → bureau) : liste cochable (avec recherche) des
  joueurs de l'effectif du club absents d'Axiobad (déjà présents exclus par numéro de licence).
  Les licenciés importés n'ont pas d'email (MyFFBaD n'en fournit pas) — pas de compte de connexion
  tant qu'il n'est pas renseigné via le bouton **« Envoyer l'invitation »** (champ email inline sur
  la liste des licenciés, pour les comptes sans email). Ce bouton n'est pas proposé pour un
  licencié mineur (basé sur `dateNaissance`) : pas de compte de connexion direct pour un mineur,
  il passe par le compte de son responsable légal.
- Nécessite l'URL de l'effectif du club sur MyFFBaD, renseignée par le bureau dans
  [Paramètres du club](#paramètres-du-club).
- Fragile par nature (dépend de la structure interne de myffbad.fr, sans garantie de stabilité) :
  toute erreur réseau ou de parsing est traitée comme "aucune correspondance" plutôt que de faire
  planter la page.

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
- **Liens familiaux élargis** (`App\Entity\LienFamilial`) : au-delà du responsable légal, « Ma
  famille » permet de lier son compte à d'autres membres (oncle-tante, grand-parent, frère/sœur,
  cousin/cousine, beau-parent...) via une demande soumise au consentement de la personne visée
  (ou de son responsable légal si elle est mineure). Lien purement déclaratif, **lecture seule**
  (prochains créneaux, statut d'adhésion) — aucun accès aux données sensibles ni action sur le
  compte de l'autre, contrairement au responsable légal. Révocable à tout moment par les deux
  parties. Journalisé (`AuditLogger::LIEN_FAMILIAL_CHANGE`).

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
- La liste des équipes est **triée par niveau** (national, puis prénational, régional,
  départemental, du meilleur numéro au moins bon), déduit du nom et de la division saisis.

### Championnats / Interclubs

- Le bureau crée des rencontres pour une équipe : numéro de **journée**, date, adversaire,
  **domicile ou extérieur**, **heure de rendez-vous**, **capitaine de la rencontre** (par défaut
  celui de l'équipe), **covoiturage** (texte libre), et le score global une fois jouée.
- **Lieu** : à domicile, le lieu se choisit dans la liste des gymnases du club (menu déroulant) ;
  à l'extérieur, c'est un champ libre (le gymnase adverse n'étant pas dans la liste du club).
- Les joueurs de l'équipe sont **convoqués** et peuvent indiquer s'ils sont présents ou non à la
  rencontre, comme pour un créneau. La **composition** (joueurs confirmés présents) est affichée
  sur la page de la rencontre.
- **Feuille de rencontre** : le bureau ajoute chaque match individuel (SH, SD, DH, DD, MX) avec
  les joueurs de l'équipe engagés, les adversaires (texte libre), le score détaillé et le résultat.
- La liste des rencontres se **filtre par équipe** (menu déroulant), avec un filtre par défaut :
  automatiquement sur son équipe si on n'en a qu'une, sinon sur son équipe préférée si elle est
  définie (réglable dans son profil). Elle est aussi **triée par niveau d'équipe**, du national au
  départemental (déduit du nom/de la division de l'équipe), comme la liste des équipes.
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
  se pré-remplit), cordage souhaité — choisi directement dans le **stock réellement disponible**
  (pas de catalogue séparé : impossible de demander un cordage que le club n'a pas), tension
  souhaitée, lieu de dépose.
- Un licencié avec le rôle **Cordeur** (avec ou sans rôle bureau) voit toutes les demandes, peut
  les modifier ou annuler à tout moment, et fait progresser leur statut : déposée → en cours →
  prête (avec prix et lieu de retour) → récupérée (le licencié peut lui-même marquer sa raquette
  récupérée).
- **Stock de cordage**, géré par les cordeurs (pas besoin du rôle bureau/stock) : articles en
  **bobine** (quantité suivie en mètres, ~10 m consommés par raquette) ou en **sachet individuel**
  (quantité suivie en sachets), avec marque, modèle, lieu de stockage et seuil d'alerte. Un même
  cordage stocké à deux endroits différents se gère avec deux articles distincts.
- Le stock n'est décompté qu'à la prise en charge par le cordeur, pas à la dépose (l'article
  choisi par le licencié est pré-sélectionné, mais le cordeur peut le changer si besoin — ex. si
  le stock a bougé entre-temps), et pour une bobine il ajuste la longueur réellement utilisée. Le
  stock est restitué automatiquement si la demande est annulée par la suite.

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

### Double authentification (MFA)

- Optionnelle, activable par chaque licencié depuis son profil, au choix : application
  d'authentification (TOTP, avec QR code) et/ou code par email — les deux pouvant être actifs en
  parallèle.

### Mon espace

- Chaque licencié dispose d'une page personnelle : ses prochains créneaux où il est inscrit
  (celles marquées « je ne viens pas » sont masquées), ses statistiques de présence, le statut de
  son adhésion, des créneaux recommandés selon son classement, les évènements à venir, et son
  historique de participation aux tournois internes et aux interclubs.
- Statut d'adhésion : affiche la saison en cours, ou à défaut (ex. adhésion réglée en avance avant
  le début de la saison) la prochaine saison si une adhésion y est déjà enregistrée.

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

- Vue mensuelle ou **vue semaine dédiée** (grille sur ordinateur, agenda journalier sur mobile —
  qui masque les jours déjà passés).
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
- **Modèles réutilisables** (sujet + message enregistrés, chargeables en un clic).
- **Pièce jointe** optionnelle, envoyée avec le message à tous les destinataires.
- **Programmation différée** : envoi à une date/heure future (commande planifiée
  `app:communication:envoyer-planifiees`, cron toutes les 5 min), annulable tant qu'il n'a pas eu
  lieu. La liste des destinataires est figée au moment de la programmation.
- **Historique** des envois (sujet, cible, statut — envoyé/en attente/annulé —, nombre de
  destinataires, échecs détaillés, pièce jointe, auteur, date), consultable sur la même page.

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
- **Historique des notifications** (menu Bureau) : chaque notification envoyée (destinataire,
  sujet, extrait, date, succès email/push), filtrable par destinataire.

### Journal d'audit

- Menu **Bureau → Journal d'audit** : trace les actions sensibles — paiement ajouté/supprimé,
  consultation ou modification des informations de santé, changement de responsable légal,
  suppression de compte (simple ou forcée), changement de rôle, correction de stock (inventaire),
  annulation de créneau, modification d'adhésion, lien familial (demande, acceptation, retrait).
- Chaque entrée indique l'auteur, la date, l'objet concerné, et l'ancienne/nouvelle valeur quand
  c'est pertinent (jamais le contenu des données de santé elles-mêmes, pour ne pas dupliquer une
  donnée sensible). Filtrable par type d'action et par auteur.

### Paramètres du club

Menu **Bureau → Paramètres du club** : nom du club, nom du club sur MyFFBaD (peut différer,
informatif), et URL de l'effectif du club sur MyFFBaD (nécessaire pour activer la
[synchronisation MyFFBaD](#synchronisation-myffbad) — les boutons de synchro restent masqués tant
qu'elle n'est pas renseignée). Réglages en base, une seule ligne, créée à la demande.

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
- [scheb/2fa-bundle](https://github.com/scheb/2fa-bundle) pour la double authentification (TOTP + email)

## Démarrage en local

```bash
docker compose build
docker compose up -d
docker compose exec php bin/console doctrine:database:create
docker compose exec php bin/console doctrine:migrations:migrate
```

L'application est accessible sur http://localhost:8080.

## Environnement de production

L'application est déployée sur **https://hac.axiobad.click** :
- HTTPS via Let's Encrypt (renouvellement automatique par cron).
- Hébergée sur une seule instance EC2 (Elastic IP, DNS Route53).
- Démarrage garanti au boot de l'instance via un service systemd (`axiobad.service`) qui relance
  la stack Docker, en plus du `restart: unless-stopped` des conteneurs.
- Emails transactionnels envoyés via Amazon SES (domaine vérifié avec DKIM).

## Instance de démonstration

**https://demo.axiobad.click** expose une instance publique avec des données 100 % fictives
(licenciés, créneaux, adhésions, interclubs...), pour laisser un prospect tester l'application
sans donner accès à de vraies données de club :
- Sert sur la même instance EC2 que la production, via le même conteneur nginx (routage par
  `server_name`) et la même base PostgreSQL (base `app_demo` séparée) — voir `compose.demo.yaml`
  et `docker/nginx/demo.conf`.
- Le conteneur applicatif tourne avec `DEMO_MODE=1`, ce qui affiche sur la page de connexion les
  identifiants d'un compte de test par rôle (bureau, entraîneur, cordeur, stock, licencié) — voir
  `App\Demo\DemoAccounts`.
- Les données sont entièrement réinitialisées chaque nuit (`app:demo:reset`, cron
  `axiobad-demo-reset`) : la commande refuse de s'exécuter si `DEMO_MODE` n'est pas activé, pour
  qu'une erreur de configuration ne puisse jamais vider la base de production.
- `noindex`/`nofollow` envoyé sur toutes les réponses de ce domaine (pas d'indexation par les
  moteurs de recherche).

## Déploiement AWS (option la moins chère)

La base de données PostgreSQL tourne dans un conteneur (pas de RDS), sur une **seule instance EC2**
(architecture ARM Graviton). C'est l'option la moins chère pour un usage 24/7 léger : quelques
euros par mois (instance + volume EBS), contre un coût bien plus élevé avec ECS Fargate ou RDS.

### Mise en place

1. Créer une instance EC2 (Amazon Linux 2023, ARM Graviton), avec :
   - un Security Group ouvrant le port 22 (SSH, restreint), 80 (HTTP) et 443 (HTTPS) ;
   - deux enregistrements DNS (Route53) pointant vers la même IP : `hac.axiobad.click` et
     `demo.axiobad.click` ;
   - le script `deploy/aws/ec2-user-data.sh` en "user data" au lancement (installe Docker, clone
     le dépôt dans `/opt/axiobad`, obtient les certificats Let's Encrypt des deux domaines,
     démarre l'application (prod + démo), et installe le service systemd `axiobad.service` pour
     un démarrage garanti au boot).
2. Configurer `.env.prod.local` sur le serveur (généré automatiquement au premier boot avec des
   secrets aléatoires ; éditer `MAILER_DSN`/`MAILER_FROM` pour un vrai envoi d'emails — voir la
   section [Configuration email](docs/guide-utilisation.md#configuration-email-production) du
   guide). `.env.demo.local` (secrets de l'instance de démo) est généré de la même façon, pas
   besoin d'y toucher.
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

`pg_dump` compressé de la base, tous les jours à 2h (`deploy/aws/backup-database.sh`, cron installé
par `ec2-user-data.sh`), envoyé vers un bucket S3 dédié (`axiobad-backups-<compte>`, privé, chiffré).
Une règle de cycle de vie S3 purge automatiquement les sauvegardes de plus de 30 jours — coût de
stockage négligeable pour une base de club (quelques Mo par dump).

### Facturation

Un budget AWS (`axiobad-mensuel`, plafond 15 $/mois) envoie une alerte email à 80 % et 100 % de
dépense réelle, plus une alerte si la dépense prévisionnelle dépasse le plafond. Au-delà de 100 %
de dépense réelle, une fonction Lambda (`axiobad-budget-autostop`, déclenchée via SNS) **arrête
automatiquement l'instance EC2** pour éviter tout dépassement supplémentaire — le site devient alors
inaccessible jusqu'à un redémarrage manuel :
```bash
aws ec2 start-instances --instance-ids i-0a06ac04603c07380 --region eu-west-3
```
(le service systemd `axiobad.service` relance la stack automatiquement une fois l'instance repartie).
