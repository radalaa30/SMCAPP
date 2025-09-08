<?php

namespace App\EventSubscriber;

use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class UserSessionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserSessionRepository $sessions,
        private readonly TokenStorageInterface $tokens,
        private readonly UrlGeneratorInterface $urls,
        private readonly EntityManagerInterface $em,
        #[Autowire('%app.sessions.ttl_seconds%')] private readonly int $ttlSeconds,
        #[Autowire('%app.sessions.max_active%')] private readonly ?int $maxActive,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class       => 'onLogout',
            // après le firewall
            KernelEvents::REQUEST    => ['onKernelRequest', -64],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        // Anti-fixation
        $session->migrate(true);

        $token = $this->tokens->getToken();
        if (!$token || !\is_object($user = $token->getUser())) {
            return;
        }

        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+'.$this->ttlSeconds.' seconds');

        $record = (new UserSession())
            ->setUser($user)
            ->setSessionId($session->getId())
            ->setCreatedAt($now)
            ->setExpiresAt($expiresAt)
            ->setUserAgent($request->headers->get('User-Agent'))
            ->setIp($request->getClientIp());

        $this->em->persist($record);
        $this->em->flush();

        // max_active: 0 = illimité ; >0 = on garde les N plus récentes
        if ($this->maxActive && $this->maxActive > 0) {
            $active = $this->sessions->findActiveByUser($user); // tri DESC (repo)
            $nowMut = new \DateTime();
            foreach (\array_slice($active, $this->maxActive) as $old) {
                if ($old->getSessionId() !== $record->getSessionId()) {
                    $old->setRevokedAt($nowMut);
                }
            }
            $this->em->flush();
        }
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) return;

        // Évite login/logout ET tes routes de redirection juste après login
        $route = $event->getRequest()->attributes->get('_route');
        if (\in_array($route, ['app_login', 'app_logout', 'app_redirect', 'app_redirect_after_login'], true)) {
            return;
        }

        $token = $this->tokens->getToken();
        if (!$token || !\is_object($token->getUser())) return;

        $session = $event->getRequest()->getSession();
        $sid = $session->getId();
        $record = $this->sessions->findOneBySessionId($sid);

        // Pas d’enregistrement => force reconnexion
        if (!$record) {
            $session->invalidate();
            $event->setResponse(new RedirectResponse($this->urls->generate('app_login')));
            return;
        }

        // Expirée / révoquée => force reconnexion
        if (!$record->isActive()) {
            $session->invalidate();
            $event->setResponse(new RedirectResponse($this->urls->generate('app_login')));
            return;
        }

        // Mise à jour d’activité (mutable)
        $record->setLastUsedAt(new \DateTime());
        $this->em->flush();
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();
        $sid = $session ? $session->getId() : null;
        if (!$sid) return;

        if ($record = $this->sessions->findOneBySessionId($sid)) {
            $record->setRevokedAt(new \DateTime());
            $this->em->flush();
        }
    }
}
