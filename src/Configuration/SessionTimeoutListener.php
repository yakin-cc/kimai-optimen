<?php

namespace App\Configuration;

use App\Entity\UserPreference;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SessionTimeoutListener implements EventSubscriberInterface
{
    private $security;
    private $session;

    public function __construct(Security $security, SessionInterface $session)
    {
        $this->security = $security;
        $this->session = $session;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $user = $this->security->getUser();

        if ($user) {
            $timeout = $user->getPreferenceValue(UserPreference::SESSION_TIMEOUT);
            if ($timeout) {
                if (is_string($timeout)) {
                    try {
                        $timeoutInterval = new \DateInterval($timeout);
                    } catch (\Exception $e) {
                        return;
                    }
                    $timeoutSeconds = ($timeoutInterval->h * 3600) + ($timeoutInterval->i * 60) + $timeoutInterval->s;
                    $this->session->migrate(false, $timeoutSeconds);
                }
            }
        }
    }
}
