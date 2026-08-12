# Politique de sécurité

Axiobad gère des données personnelles de licenciés (dont, pour certains mineurs, des données de
santé). La sécurité de l'application est prise au sérieux — merci de signaler toute vulnérabilité
de façon responsable plutôt que de l'exploiter ou de la divulguer publiquement.

## Signaler une vulnérabilité

**Ne pas ouvrir d'issue ou de pull request publique pour une faille de sécurité.**

Contacter directement : **contact@axioweb.fr**

Merci d'indiquer si possible :
- une description du problème et de son impact potentiel ;
- les étapes pour le reproduire (ou un proof of concept) ;
- la version/le commit concerné, si connu.

Un accusé de réception sera envoyé sous quelques jours, avec un suivi jusqu'à la résolution.
Une divulgation publique coordonnée pourra être envisagée une fois le correctif déployé.

## Périmètre

- Le code source de ce dépôt (`tfageo18/Axiobad`).
- L'instance de production : https://hac.axiobad.click

## Hors périmètre

- Attaques par déni de service (DoS/DDoS).
- Ingénierie sociale visant les utilisateurs ou l'hébergeur.
- Vulnérabilités dans des dépendances tierces déjà publiquement connues et non corrigées en amont
  (à signaler plutôt directement au projet concerné, avec un lien en copie ici si ça affecte
  Axiobad).

## Versions supportées

Il n'y a pas de gestion de versions multiples : seule la version déployée en production (issue de
la branche `main`) est maintenue et reçoit des correctifs de sécurité.
