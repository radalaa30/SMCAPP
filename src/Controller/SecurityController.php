<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    //////////////////////////////////////////////////////////////////////////////////////////////////////
    #[Route('/redirect', name: 'app_redirect')]
    public function redirectBasedOnRole(): Response
    {
       
        if ($this->isGranted('ROLE_ADMIN')) {
            //dd('admin');
            return $this->redirectToRoute('admin_dashboard');
        }
        if ($this->isGranted('ROLE_CONSULTATION')) {
            //dd('admin');
            return $this->redirectToRoute('consultation_dashboard');
        }
        if ($this->isGranted('ROLE_CARISTE')) {

            return $this->redirectToRoute('cariste_dashboard');
        }

        // Si aucun rôle correspondant, redirection vers login
        return $this->redirectToRoute('app_login');
    }
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils,Request $request): Response
    {
        //echo 'idi';
        //die();
        
        if ($request->isMethod('POST')) {
         // Méthode 1 : Utiliser dump() pour voir toutes les données POST
         dump($request->request->all());
        }

        ($this->getUser());
        // Si l'utilisateur est déjà connecté, redirigez-le selon son rôle
        if ($this->getUser()) {
            
            return $this->redirectToRoute('app_redirect');  // Cette route gère la redirection après login
        }
    
        // Obtenez l'erreur de connexion s'il y en a une
        $error = $authenticationUtils->getLastAuthenticationError();
        // Récupérez le dernier nom d'utilisateur entré
        $lastUsername = $authenticationUtils->getLastUsername();
    
        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }


    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
    #[Route('/redirect-after-login', name: 'app_redirect_after_login')]
    public function redirectAfterLogin(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }
        if ($this->isGranted('ROLE_CONSULTATION')) {
            return $this->redirectToRoute('consultation_dashboard');
        }
        if ($this->isGranted('ROLE_CARISTE')) {
            return $this->redirectToRoute('cariste_dashboard');
        }

        return $this->redirectToRoute('app_login');
    }
}
