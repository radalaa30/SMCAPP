<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PreparateurController extends AbstractController
{
    #[Route('/preparateur', name: 'preparateur_dashboard')]
    public function index(): Response
    {
        return $this->render('preparateur/index.html.twig', [
            'controller_name' => 'PreparateurController',
        ]);
    }
}
