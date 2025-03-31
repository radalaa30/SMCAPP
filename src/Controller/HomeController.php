<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Demande;
use App\Form\DemandeType;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\DemandeRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class HomeController extends AbstractController
{
    #[Route('/demandes', name: 'demande.liste')]
    public function demandes(Request $request, DemandeRepository $repository): Response
    {
        $listeDemande = $repository->findByExampleFieldExcludingValue('V');
        //$listeDemande = $repository->findAll();
        return $this->render('home/index.html.twig', [
        'listeDemande' => $listeDemande
        ]);
    }
    #[Route('/demandes/id', name: 'demande.listeId', requirements:['id' => '\d+'])]
    public function index(Request $request,int $id, DemandeRepository $repository): Response
    {
        $demande = $repository->find($id);
               $listeDemande = $repository->findAll();
        return $this->render('home/index.html.twig', [
        'listeDemande' => $listeDemande
        ]);
    }
    
    #[Route('/reapro/create', name: 'app_reapro_create',)]
    public function Demande_create(Request $request, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
       $demande = new Demande();
       $form = $this->createForm(DemandeType::class, $demande);
       $form->handleRequest($request);
       if($form->isSubmitted() && $form->isValid()){
        $demande->setCreateAt(new \DateTimeImmutable());
        $demande->setZone('B');
        $demande->setEtat('yes');
        $em->persist($demande);
        $em->flush();
        $this->addFlash('success','La demande est bien envoyé');
        return $this->redirectToRoute('demande.liste');
       }
       return $this->render('home/create.html.twig', [
        'form' => $form

       ]);
    } 

    #[Route('/reapro/{id}', name: 'app_reapro_edit', requirements:['id' => '\d+'])]
    public function Demande_edit(demande $demande, Request $request, EntityManagerInterface $em)
    {
    $form = $this->createForm(DemandeType::class, $demande);
       $form->handleRequest($request);
       if($form->isSubmitted() && $form->isValid()){
        $demande->setCreateAt(new \DateTimeImmutable());
        $demande->setZone('V');
        $em->persist($demande);
        $em->flush();
        $this->addFlash('success','La demande est bien envoyé');
        return $this->redirectToRoute('demande.liste');
       }
       return $this->render('home/create.html.twig', [
        'form' => $form
       ]);
    } 
}
