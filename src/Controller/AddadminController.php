<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\DemandeReapproRepository;
use Symfony\Component\VarDumper\VarDumper;
use App\Entity\DemandeReappro;
use App\Form\Validation;
use App\Form\ValidationType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
//use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Security;
//controller pour traiter l'anomalie des vieux PDA
#[IsGranted('ROLE_ADMIN')]
class AddadminController extends AbstractController
{

    
    //Demande de reappro par le preparateur
    #[Route('/addadmin', name: 'addadmin_reappro')]
    public function AddadminReappro(request $request, EntityManagerInterface $em,DemandeReappro $Demande): Response
    {
        echo 'idi';
        die();
        //$this->denyAccessUnlessGranted('ROLE_USER');
        // Récupérer l'objet utilisateur
        //$user = $this->getUser();
        // Vérifier si l'utilisateur est connecté
       // if ($user) {
            // Extraire les données de l'objet utilisateur
       //     $userId = $user->getId();         // Integer
        //    $username = $user->getUsername(); // String
        //    $email = $user->getEmail();       // String
            // Afficher les données (par exemple, pour le débogage)
        //} else {
            // Gestion des cas où l'utilisateur n'est pas connecté
         //   return new Response('User not authenticated.');
        //}

        $form = $this->createForm(ValidationType::class, $Demande);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
        $Demande->setIdReappro(25);
        $Demande->setidPreparateur(5526);
        $Demande->setUsernamePrep('Gerald');
        //$Demande->setIdCariste($userId);
        $Demande->setSonPicking('NON');
        ///$listeDemande->setAdresse('152-25-36');
        $Demande->setStatut('A');
        $Demande->setCreateAt(new \DateTimeImmutable());
        //$validation->getUpdateAt($id);
        //$validation->getUpdateAt($id);
        $em->persist($Demande);
        $em->flush();
        $this->addFlash('success','La demande est bien envoyé');
        return $this->redirectToRoute('addadmin_reappro');
        }

        return $this->render('addadmin/index.html.twig', [
            'form' => $form
    
           ]);
    }

   
    
}
