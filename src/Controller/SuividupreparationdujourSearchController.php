<?php

namespace App\Controller;

use App\Form\SuiviPreparationSearchType;
use App\Repository\SuividupreparationdujourSearchRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;

    #[Route('/admin')]
class SuividupreparationdujourSearchController extends AbstractController
    {
        #[Route('/suivi/litige/{id}', name: 'app_suivi_litige', methods: ['POST'])]
    public function toggleLitige(
        Request $request, 
        Suividupreparationdujour $suivi, 
        EntityManagerInterface $entityManager
    ): Response
    {
        $litige = $request->request->has('litige');
        $suivi->setLitige($litige);
        $entityManager->flush();

        // Ajoutez un message flash optionnel
        $this->addFlash(
            'success',
            'Le statut du litige a été mis à jour avec succès.'
        );

        // Redirigez vers la page précédente
        return $this->redirect($request->headers->get('referer'));
    }
    #[Route('/suivi/preparation/search', name: 'app_suivi_preparation_search')]
    public function index(
        Request $request,
        SuividupreparationdujourSearchRepository $repository,
        PaginatorInterface $paginator
    ): Response {
        $form = $this->createForm(SuiviPreparationSearchType::class);
        $form->handleRequest($request);

        $results = $repository->findBySearchCriteria($request->query->all());

        $pagination = $paginator->paginate( 
            $results,
            $request->query->getInt('page', 1),
            10 // Nombre d'éléments par page
        );
      
       // dd($pagination);

        return $this->render('suividupreparationdujour_search/index.html.twig', [
            'form' => $form->createView(),
            'pagination' => $pagination,
        ]);
    }
}