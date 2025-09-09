<?php

namespace App\Controller;

use App\Repository\SuividupreparationdujourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\PreparateurRepository; 
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\HttpFoundation\JsonResponse;


class PreparateurParClientController extends AbstractController
{
   
    #[Route('suivi/preparateurs/{codeClient}', name: 'get_preparateurs', methods: ['GET'])]
    public function getPreparateurs(
        Request $request, 
        SuividupreparationdujourRepository $suividupreparationdujourRepository
    ): JsonResponse
    {
    
        $codeClient = $request->query->get('codeClient');

        echo  $codeClient;
        
        if (!$codeClient) {
            return new JsonResponse(['error' => 'Code client manquant'], 400);
        }
        
        // Supposons que vous ayez une méthode findByCodeClient dans votre repository
        $preparateurs = $suividupreparationdujourRepository->findByCodeClient($codeClient);
        
        // Formater les données pour le JSON
        $data = array_map(function($preparateur) {
            return [
                'id' => $preparateur->getId(),
                'nom' => $preparateur->getNom(),
                'prenom' => $preparateur->getPrenom(),
                // Ajoutez d'autres champs si nécessaire
            ];
        }, $preparateurs);
        
        return new JsonResponse($data);
    }
}
