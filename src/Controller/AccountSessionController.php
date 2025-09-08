<?php

namespace App\Controller;

use App\Repository\UserSessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class AccountSessionController extends AbstractController
{
    public function __construct(private readonly UserSessionRepository $sessions) {}

    #[Route('/account/sessions', name: 'account_sessions', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $list = $this->sessions->findActiveByUser($user);
        $currentSid = $request->getSession()->getId();

        return $this->render('account/sessions.html.twig', [
            'sessions' => $list,
            'current_sid' => $currentSid,
        ]);
    }

    #[Route('/account/sessions/{id}/revoke', name: 'account_sessions_revoke', methods: ['POST'])]
    public function revoke(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->isCsrfTokenValid('revoke_session_'.$id, $request->request->get('_token')) || $this->createAccessDeniedException();

        $em = $this->sessions->getEntityManager();
        $session = $this->sessions->find($id);
        if ($session && $session->getUser() === $this->getUser()) {
            $session->setRevokedAt(new \DateTime());
            $em->flush();

            // Si c’était la session courante, invalide aussi côté PHP
            if ($session->getSessionId() === $request->getSession()->getId()) {
                $request->getSession()->invalidate();
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->redirectToRoute('account_sessions');
    }
}
