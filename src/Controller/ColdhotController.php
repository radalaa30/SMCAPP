<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
class ColdhotController extends AbstractController
{
    #[Route('/coldhot', name: 'app_coldhot')]
    public function index(): Response
    {
        return $this->render('coldhot/index.html.twig', [
            'controller_name' => 'ColdhotController',
        ]);
    }
}
