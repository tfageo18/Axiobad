<?php

namespace App\EventSubscriber;

use App\Entity\Licencie;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

class ForcePasswordChangeSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ROUTES = ['app_change_password', 'app_logout', 'app_activation'];

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof Licencie || !$user->mustChangePassword()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_change_password')));
    }
}
