<?php

namespace App\Controller;

use App\Entity\DemandeReappro;
use App\Form\DemandeReappro1Type;
use App\Repository\DemandeReapproRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[IsGranted('ROLE_ADMIN')]
#[IsGranted('ROLE_CARISTE')]
#[Route('/demande/reappro')]
class DemandeReapproController extends AbstractController
{
    #[Route('/', name: 'app_demande_reappro_index', methods: ['GET'])]
    public function index(DemandeReapproRepository $demandeReapproRepository): Response
    {
        return $this->render('demande_reappro/index.html.twig', [
            'demande_reappros' => $demandeReapproRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_demande_reappro_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $demandeReappro = new DemandeReappro();
        $form = $this->createForm(DemandeReappro1Type::class, $demandeReappro);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($demandeReappro);
            $entityManager->flush();

            return $this->redirectToRoute('app_demande_reappro_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('demande_reappro/new.html.twig', [
            'demande_reappro' => $demandeReappro,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_demande_reappro_show', methods: ['GET'])]
    public function show(DemandeReappro $demandeReappro): Response
    {
        return $this->render('demande_reappro/show.html.twig', [
            'demande_reappro' => $demandeReappro,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_demande_reappro_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DemandeReappro $demandeReappro, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DemandeReappro1Type::class, $demandeReappro);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('demande_reappro/edit.html.twig', [
            'demande_reappro' => $demandeReappro,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_demande_reappro_delete', methods: ['POST'])]
    public function delete(Request $request, DemandeReappro $demandeReappro, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$demandeReappro->getId(), $request->getPayload()->get('_token'))) {
            $entityManager->remove($demandeReappro);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_dashboard', [], Response::HTTP_SEE_OTHER);
    }
}
