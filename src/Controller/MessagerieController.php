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
 * Messagerie privée à deux entre licenciés. Contenu visible des seuls participants — pas d'accès
 * bureau, y compris pour la modération.
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

        $autre = $licencieRepository->find($request->request->get('licencie'));
        if (!$autre || $autre === $moi) {
            $this->addFlash('error', 'Licencié invalide.');

            return $this->redirectToRoute('app_messagerie_index');
        }

        $conversation = $conversationRepository->findEntre($moi, $autre);
        if (!$conversation) {
            $conversation = (new Conversation())->setParticipant1($moi)->setParticipant2($autre);
            $entityManager->persist($conversation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conversation->getId()]);
    }

    #[Route('/{id}', name: 'app_messagerie_conversation', methods: ['GET'])]
    public function conversation(Conversation $conversation, MessageRepository $messageRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();
        $this->refuserSiPasParticipant($conversation, $moi);

        $conversation->marquerVuPar($moi);
        $entityManager->flush();

        return $this->render('messagerie/conversation.html.twig', [
            'conversation' => $conversation,
            'autre' => $conversation->getAutreParticipant($moi),
            'messages' => $messageRepository->findRecents($conversation),
            'moi' => $moi,
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
            'messages' => $messageRepository->findRecents($conversation),
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

    private function refuserSiPasParticipant(Conversation $conversation, Licencie $licencie): void
    {
        if (!$conversation->estParticipant($licencie)) {
            throw $this->createAccessDeniedException();
        }
    }
}
