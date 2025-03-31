<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CaristeController extends AbstractController
{
    #[Route('/cariste', name: 'cariste_dashboard')]
    public function index(): Response
    {
        return $this->render('cariste/index.html.twig', [
            'controller_name' => 'CaristeController',
        ]);
    }
}
