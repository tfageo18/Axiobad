<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Licencie;
use App\Entity\Message;
use App\Repository\ConversationRepository;
use App\Repository\LicencieRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Messagerie privée entre licenciés — discussions à deux ou de groupe (1 à N destinataires).
 * Contenu visible des seuls participants — pas d'accès bureau, y compris pour la modération.
 */
#[Route('/messagerie')]
class MessagerieController extends AbstractController
{
    #[Route('', name: 'app_messagerie_index', methods: ['GET'])]
    public function index(ConversationRepository $conversationRepository, LicencieRepository $licencieRepository): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();

        $conversations = $conversationRepository->findPourLicencie($moi);

        return $this->render('messagerie/index.html.twig', [
            'conversations' => $conversations,
            'moi' => $moi,
            'licencies' => array_values(array_filter($licencieRepository->findBy([], ['nom' => 'ASC']), static fn (Licencie $l) => $l !== $moi)),
        ]);
    }

    #[Route('/nouvelle', name: 'app_messagerie_nouvelle', methods: ['POST'])]
    public function nouvelle(Request $request, LicencieRepository $licencieRepository, ConversationRepository $conversationRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('messagerie-nouvelle', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_messagerie_index');
        }

        /** @var Licencie $moi */
        $moi = $this->getUser();

        $destinataires = [];
        foreach ($request->request->all('licencies') as $id) {
            $licencie = $licencieRepository->find($id);
            if ($licencie && $licencie !== $moi && !in_array($licencie, $destinataires, true)) {
                $destinataires[] = $licencie;
            }
        }

        if (!$destinataires) {
            $this->addFlash('error', 'Choisissez au moins un destinataire.');

            return $this->redirectToRoute('app_messagerie_index');
        }

        $titre = trim((string) $request->request->get('titre')) ?: null;

        // Discussion à deux sans titre : on réutilise la conversation existante s'il y en a
        // déjà une, plutôt que d'en recréer une à chaque fois.
        if (1 === count($destinataires) && !$titre) {
            $conversation = $conversationRepository->findEntre($moi, $destinataires[0]);
            if ($conversation) {
                return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
            }
        }

        $conversation = (new Conversation())->setTitre($titre)->setCreateur($moi);
        $conversation->ajouterParticipant($moi, estAdmin: true);
        foreach ($destinataires as $destinataire) {
            $conversation->ajouterParticipant($destinataire);
        }

        $entityManager->persist($conversation);
        $entityManager->flush();

        return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
    }

    #[Route('/{id}', name: 'app_messagerie_conversation', methods: ['GET'])]
    public function conversation(Conversation $conversation, MessageRepository $messageRepository, LicencieRepository $licencieRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();
        $this->refuserSiPasParticipant($conversation, $moi);

        $conversation->marquerVuPar($moi);
        $entityManager->flush();

        $participants = $conversation->getAutresParticipants(null);
        $ajoutables = array_values(array_filter(
            $licencieRepository->findBy([], ['nom' => 'ASC']),
            static fn (Licencie $l) => !in_array($l, $participants, true)
        ));

        return $this->render('messagerie/conversation.html.twig', [
            'conversation' => $conversation,
            'nom' => $conversation->getNomAffiche($moi),
            'autresParticipants' => $conversation->getAutresParticipants($moi),
            'messages' => $messageRepository->findRecents($conversation, pour: $moi),
            'moi' => $moi,
            'ajoutables' => $ajoutables,
        ]);
    }

    /**
     * Appelé en polling par le JS de la page de conversation (toutes les quelques secondes) :
     * renvoie uniquement le HTML de la liste des messages, pour remplacer la zone d'affichage
     * sans jamais toucher au formulaire de saisie.
     */
    #[Route('/{id}/actualiser', name: 'app_messagerie_actualiser', methods: ['GET'])]
    public function actualiser(Conversation $conversation, MessageRepository $messageRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();
        $this->refuserSiPasParticipant($conversation, $moi);

        $conversation->marquerVuPar($moi);
        $entityManager->flush();

        $response = $this->render('messagerie/_messages.html.twig', [
            'messages' => $messageRepository->findRecents($conversation, pour: $moi),
            'conversation' => $conversation,
            'moi' => $moi,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/{id}/envoyer', name: 'app_messagerie_envoyer', methods: ['POST'])]
    public function envoyer(Request $request, Conversation $conversation, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();
        $this->refuserSiPasParticipant($conversation, $moi);

        if (!$this->isCsrfTokenValid('messagerie-envoyer-'.$conversation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
        }

        $contenu = trim((string) $request->request->get('contenu'));
        if ('' === $contenu) {
            return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
        }

        $message = (new Message())
            ->setConversation($conversation)
            ->setExpediteur($moi)
            ->setContenu(mb_substr($contenu, 0, 4000));

        $conversation->enregistrerNouveauMessage($moi, $message->getEnvoyeLe());

        $entityManager->persist($message);
        $entityManager->flush();

        return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
    }

    #[Route('/{id}/quitter', name: 'app_messagerie_quitter', methods: ['POST'])]
    public function quitter(Request $request, Conversation $conversation, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();
        $this->refuserSiPasParticipant($conversation, $moi);

        if (!$this->isCsrfTokenValid('messagerie-quitter-'.$conversation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
        }

        $etaitGroupe = $conversation->estGroupe();
        $etaitAdmin = $conversation->estAdmin($moi);
        $conversation->retirerParticipant($moi);

        if ($conversation->getParticipants()->isEmpty()) {
            // Plus personne dans la conversation : elle est supprimée (avec ses messages).
            $entityManager->remove($conversation);
        } elseif ($etaitAdmin && !$conversation->getAdmins()) {
            // L'admin qui part était le seul : le rôle passe au participant restant le plus ancien.
            $conversation->getParticipants()->first()->setEstAdmin(true);
        }

        $entityManager->flush();

        $this->addFlash('success', $etaitGroupe ? 'Vous avez quitté le groupe.' : 'Conversation supprimée.');

        return $this->redirectToRoute('app_messagerie_index');
    }

    /**
     * Ajoute un ou plusieurs participants à une conversation existante — réservé à l'admin du
     * groupe. Fonctionne aussi sur une discussion à deux, qui devient alors un groupe : l'historique
     * existant est conservé pour tout le monde, la case "sans l'historique" ne s'applique qu'aux
     * nouveaux arrivants.
     */
    #[Route('/{id}/ajouter', name: 'app_messagerie_ajouter', methods: ['POST'])]
    public function ajouter(Request $request, Conversation $conversation, LicencieRepository $licencieRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();
        $this->refuserSiPasAdmin($conversation, $moi);

        if (!$this->isCsrfTokenValid('messagerie-ajouter-'.$conversation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
        }

        $avecHistorique = (bool) $request->request->get('avecHistorique');
        $ajoutes = 0;
        foreach ($request->request->all('licencies') as $id) {
            $licencie = $licencieRepository->find($id);
            if ($licencie && !$conversation->estParticipant($licencie)) {
                $conversation->ajouterParticipant($licencie, avecHistorique: $avecHistorique);
                ++$ajoutes;
            }
        }

        if ($ajoutes > 0) {
            $entityManager->flush();
            $this->addFlash('success', $ajoutes > 1 ? $ajoutes.' personnes ajoutées à la conversation.' : 'Personne ajoutée à la conversation.');
        }

        return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
    }

    /**
     * Retire un participant du groupe — réservé à l'admin (pour se retirer soi-même, voir
     * `quitter()`).
     */
    #[Route('/{id}/retirer/{licencieId}', name: 'app_messagerie_retirer', methods: ['POST'])]
    public function retirer(Request $request, Conversation $conversation, int $licencieId, LicencieRepository $licencieRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();
        $this->refuserSiPasAdmin($conversation, $moi);

        if (!$this->isCsrfTokenValid('messagerie-retirer-'.$conversation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
        }

        $cible = $licencieRepository->find($licencieId);
        if ($cible === $moi) {
            $this->addFlash('error', 'Utilisez « Quitter le groupe » pour vous retirer vous-même.');

            return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
        }

        if ($cible && $conversation->estParticipant($cible)) {
            $conversation->retirerParticipant($cible);
            $entityManager->flush();
            $this->addFlash('success', $cible->getNomComplet().' a été retiré(e) de la conversation.');
        }

        return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
    }

    /**
     * Transmet le rôle d'admin du groupe à un autre participant.
     */
    #[Route('/{id}/promouvoir/{licencieId}', name: 'app_messagerie_promouvoir', methods: ['POST'])]
    public function promouvoir(Request $request, Conversation $conversation, int $licencieId, LicencieRepository $licencieRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();
        $this->refuserSiPasAdmin($conversation, $moi);

        if (!$this->isCsrfTokenValid('messagerie-promouvoir-'.$conversation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
        }

        $cible = $licencieRepository->find($licencieId);
        if ($cible && $conversation->estParticipant($cible)) {
            $conversation->transmettreAdmin($moi, $cible);
            $entityManager->flush();
            $this->addFlash('success', $cible->getNomComplet().' est désormais admin du groupe.');
        }

        return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
    }

    private function refuserSiPasParticipant(Conversation $conversation, Licencie $licencie): void
    {
        if (!$conversation->estParticipant($licencie)) {
            throw $this->createAccessDeniedException();
        }
    }

    private function refuserSiPasAdmin(Conversation $conversation, Licencie $licencie): void
    {
        $this->refuserSiPasParticipant($conversation, $licencie);
        if (!$conversation->estAdmin($licencie)) {
            throw $this->createAccessDeniedException();
        }
    }
}
