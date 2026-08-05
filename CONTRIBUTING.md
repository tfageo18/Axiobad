# Contribuer

Axiobad n'est **pas un projet open source** : il est développé et maintenu par une seule
personne, et le code n'est disponible publiquement que pour consultation — voir [LICENSE](LICENSE).
Aucun droit de réutilisation, de redistribution ou de fork n'est accordé.

En revanche, **les issues et les pull requests sont les bienvenues** :

- **Issue** : pour signaler un bug, proposer une amélioration ou poser une question sur le
  fonctionnement.
- **Pull request** : pour proposer une correction ou une petite évolution. Toute PR reste soumise
  à relecture et validation avant d'être fusionnée — il n'y a pas d'engagement à l'accepter telle
  quelle. En la soumettant, tu acceptes qu'elle soit intégrée au projet dans les mêmes conditions
  que le reste du code (voir [LICENSE](LICENSE)).

## Avant d'ouvrir une PR

1. Voir la section [Démarrage en local](README.md#démarrage-en-local) du README pour faire tourner
   le projet.
2. Vérifier que le code passe les vérifications de base :
   ```bash
   docker compose exec php bin/console lint:twig templates/
   docker compose exec php bin/console lint:container
   ```
3. Décrire clairement le problème résolu ou la fonctionnalité ajoutée dans la description de la PR.
