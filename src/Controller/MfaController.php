<?php

namespace App\Controller;

use App\Entity\Licencie;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion de la double authentification (MFA) par le licencié lui-même, depuis son profil.
 * Optionnelle, au choix : par email et/ou par application TOTP (Google Authenticator, etc.),
 * les deux pouvant être activées en parallèle.
 */
#[Route('/mon-compte/mfa')]
class MfaController extends AbstractController
{
    private const SESSION_KEY_SECRET_EN_ATTENTE = 'mfa_totp_secret_en_attente';

    #[Route('', name: 'app_mfa', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('mfa/index.html.twig', [
            'licencie' => $this->getUser(),
        ]);
    }

    #[Route('/totp/preparer', name: 'app_mfa_totp_preparer', methods: ['POST'])]
    public function totpPreparer(RequestStack $requestStack, TotpAuthenticatorInterface $totpAuthenticator): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        if (!$this->isCsrfTokenValid('mfa-totp-preparer', (string) $requestStack->getCurrentRequest()?->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_mfa');
        }

        $secret = $totpAuthenticator->generateSecret();
        $requestStack->getSession()->set(self::SESSION_KEY_SECRET_EN_ATTENTE, $secret);

        // Secret temporaire, en mémoire seulement (non flush), pour générer le QR code de la
        // bonne configuration sans encore l'activer.
        $licencie->setTotpSecret($secret);

        return $this->redirectToRoute('app_mfa_totp_confirmer');
    }

    #[Route('/totp/confirmer', name: 'app_mfa_totp_confirmer', methods: ['GET'])]
    public function totpConfirmer(RequestStack $requestStack, TotpAuthenticatorInterface $totpAuthenticator): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        $secret = $requestStack->getSession()->get(self::SESSION_KEY_SECRET_EN_ATTENTE);
        if (!$secret) {
            $this->addFlash('error', "Aucune activation TOTP en cours. Recommencez depuis votre profil.");

            return $this->redirectToRoute('app_mfa');
        }

        $licencie->setTotpSecret($secret);

        $qrCode = new QrCode($totpAuthenticator->getQRContent($licencie), size: 260, margin: 10);
        $qrCodeDataUri = (new PngWriter())->write($qrCode)->getDataUri();

        return $this->render('mfa/totp_confirmer.html.twig', [
            'secret' => $secret,
            'qrCodeDataUri' => $qrCodeDataUri,
        ]);
    }

    #[Route('/totp/activer', name: 'app_mfa_totp_activer', methods: ['POST'])]
    public function totpActiver(RequestStack $requestStack, EntityManagerInterface $entityManager, TotpAuthenticatorInterface $totpAuthenticator): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();
        $request = $requestStack->getCurrentRequest();

        if (!$this->isCsrfTokenValid('mfa-totp-activer', (string) $request?->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_mfa');
        }

        $secret = $requestStack->getSession()->get(self::SESSION_KEY_SECRET_EN_ATTENTE);
        if (!$secret) {
            $this->addFlash('error', "Aucune activation TOTP en cours. Recommencez depuis votre profil.");

            return $this->redirectToRoute('app_mfa');
        }

        $licencie->setTotpSecret($secret);
        $code = (string) $request?->request->get('code');

        if (!$totpAuthenticator->checkCode($licencie, $code)) {
            $this->addFlash('error', 'Code incorrect. Vérifiez l\'heure de votre téléphone et réessayez.');

            return $this->redirectToRoute('app_mfa_totp_confirmer');
        }

        $licencie->setTotpAuthEnabled(true);
        $entityManager->flush();
        $requestStack->getSession()->remove(self::SESSION_KEY_SECRET_EN_ATTENTE);

        $this->addFlash('success', 'Double authentification par application activée.');

        return $this->redirectToRoute('app_mfa');
    }

    #[Route('/totp/desactiver', name: 'app_mfa_totp_desactiver', methods: ['POST'])]
    public function totpDesactiver(RequestStack $requestStack, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        if (!$this->isCsrfTokenValid('mfa-totp-desactiver', (string) $requestStack->getCurrentRequest()?->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_mfa');
        }

        $licencie->setTotpAuthEnabled(false);
        $licencie->setTotpSecret(null);
        $entityManager->flush();

        $this->addFlash('success', 'Double authentification par application désactivée.');

        return $this->redirectToRoute('app_mfa');
    }

    #[Route('/email/activer', name: 'app_mfa_email_activer', methods: ['POST'])]
    public function emailActiver(RequestStack $requestStack, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        if (!$this->isCsrfTokenValid('mfa-email-activer', (string) $requestStack->getCurrentRequest()?->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_mfa');
        }

        $licencie->setEmailAuthEnabled(true);
        $entityManager->flush();

        $this->addFlash('success', 'Double authentification par email activée.');

        return $this->redirectToRoute('app_mfa');
    }

    #[Route('/email/desactiver', name: 'app_mfa_email_desactiver', methods: ['POST'])]
    public function emailDesactiver(RequestStack $requestStack, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        if (!$this->isCsrfTokenValid('mfa-email-desactiver', (string) $requestStack->getCurrentRequest()?->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_mfa');
        }

        $licencie->setEmailAuthEnabled(false);
        $entityManager->flush();

        $this->addFlash('success', 'Double authentification par email désactivée.');

        return $this->redirectToRoute('app_mfa');
    }
}
