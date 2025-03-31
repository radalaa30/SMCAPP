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
use App\Form\DemandeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
//use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Security;

class ReapproController extends AbstractController
{

    #[Route('/', name: 'app_reappro')]
    public function index(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, DemandeReapproRepository $repositoryReappro): Response
    {
        // Vérifie si l'utilisateur a les droits d'accès nécessaires
        $this->denyAccessUnlessGranted('ROLE_USER');
    
        // Logique pour vérifier la liste des reappros
        $user = $this->getUser();
    
        // Vérifier si l'utilisateur est connecté
        if (!$user) {
            // Gestion des cas où l'utilisateur n'est pas connecté
            return new Response('User not authenticated.');
        }
    
        // Extraire les données de l'utilisateur
        $userId = $user->getId();
        $userusername = $user->getUsername();
        
        // Essayer de trouver une demande par idCariste et statut excluant 'V' et 'E'
        $demandeuser = $repositoryReappro->findOldestByStatusExcludingVAndEBYIDcariste($userId);
        
        if ($demandeuser !== null) {
            // Si une demande est trouvée, rendre la vue avec cette demande
            return $this->render('reappro/index.html.twig', [
                'results' => [$demandeuser]
            ]);
        }
        
        // Essayer de trouver une demande par statut excluant 'V' et 'E'
        $demande = $repositoryReappro->findOldestByStatusExcludingVAndE($userId);
        
        if ($demande !== null) {
            // Si une demande est trouvée, rendre la vue avec cette demande
            return $this->render('reappro/index.html.twig', [
                'results' => [$demande]
            ]);
        }
        
        // Essayer de trouver une demande par statut 'A'
        $demandeAA = $repositoryReappro->findOldestByStatusA($userId,$userusername);
        
        if ($demandeAA !== null) {
            // Si une demande est trouvée, rendre la vue avec cette demande
            return $this->render('reappro/index.html.twig', [
                'results' => [$demandeAA]
            ]);
        }
        
        // Si aucune demande n'est trouvée, rendre la vue avec un message approprié
        $this->addFlash('notice', 'Aucune palette');
        
        return $this->render('reappro/index.html.twig', [
            'results' => []
        ]);
    }
    

    #[Route('/validation/{id}', name: 'edit_validation', requirements:['id' => '\d+'])]
    public function editReappro(request $request,int $id, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, DemandeReapproRepository $RepositoryReappro, DemandeReappro $listeDemande): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
       
        //recupurer id_user
         // Récupérer l'objet utilisateur
         $user = $this->getUser();
         // Vérifier si l'utilisateur est connecté
         if ($user) {
             // Extraire les données de l'objet utilisateur
             $userId= $user->getId();         // Integer
             $username = $user->getUsername(); // String
             $email = $user->getEmail();       // String
             // Afficher les données (par exemple, pour le débogage)
         } else {
             // Gestion des cas où l'utilisateur n'est pas connecté
             return new Response('User not authenticated.');
         }
       $form = $this->createForm(ValidationType::class, $listeDemande);
        $form->handleRequest($request);
        $adresseDist = $request->request->get('adresse_dist');
        $adresseConvertie = preg_replace('/^10/', 'C', $form->get('adresse')->getData());
        $adressereçu = $request->query->get('adresseDist'); 
        $adresseDist = preg_replace('/^10/', 'C', $request->query->get('adresseDist'));
        if(strpos($adressereçu, 'C') !== 0){
            if ( ($adresseConvertie == $adresseDist) ) {
                
                if($form->isSubmitted() && $form->isValid()){
                
                    //$validation->setCreateAt(new \DateTimeImmutable())
                    $listeDemande->setIdReappro(25);
                    $listeDemande->setIdCariste($userId);
                    $listeDemande->setUsernameCariste($username);
                    $listeDemande->setSonPicking('NON');
                    $listeDemande->setUpdateAt(new \DateTimeImmutable());
                    ///$listeDemande->setAdresse('152-25-36');
                    $listeDemande->setStatut('V');
                    $em->persist($listeDemande);
                    $em->flush();
                    $this->addFlash('success','La demande est bien envoyé');
                    return $this->redirectToRoute('app_reappro');
                }
            return $this->render('reappro/Validation.html.twig', [
                'form' => $form
        
               ]);
            }else{
                $this->addFlash('error', 'L\'adresse est incorrecte');
                return $this->redirectToRoute('app_reappro');
            }
        }else{
            $this->addFlash('error', 'L\'adresse est incorrecte');
                return $this->redirectToRoute('app_reappro');
        }

           
       
      
       
    }
    #[Route('/add-rea', name: 'add_reappro')]
    public function AddReappro(Request $request, EntityManagerInterface $em, DemandeReappro $Demande): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $user = $this->getUser();
        if (!$user) {
            return new Response('User not authenticated.');
        }
        $userId = $user->getId();
        $username = $user->getUsername();
    
        //si la requette request vien du button valider 

        $form = $this->createForm(ValidationType::class, $Demande);
        $form->handleRequest($request);
        $adresseConvertie = preg_replace('/^10/', 'C', $form->get('adresse')->getData());
        // Vérifier l'existence d'une demande pour cette adresse
        $existingDemande = $em->getRepository(DemandeReappro::class)
        ->findExistingDemandeByAdresse($adresseConvertie);
       
    
        if ($request->request->has('valider') || $adresseConvertie ==''  ) {          
            $this->addFlash(
                'warning',
                sprintf(
                    'Palette est déjà en cours ou la demande est vide',
                    $existingDemande->getAdresse(),
                    $existingDemande->getStatut()
                )
            );
            return $this->render('reappro/createReappro.html.twig', [
                'form' => $form
            ]);
           
        }else{
                    
                    if ($form->isSubmitted() && $form->isValid()) {
                    //convertire 
                    $adresseConvertie = preg_replace('/^10/', 'C', $form->get('adresse')->getData());
                    //Si la demande est vide, ne  rien faire 
                    if ($adresseConvertie =='') {
                      
                    }
                    
                    if ($existingDemande) {
                        $this->addFlash(
                            'warning',
                            sprintf(
                                'Palette est déjà en cours de déplacement pour l\'adresse %s',
                                $existingDemande->getAdresse(),
                                $existingDemande->getStatut()
                            )
                        );
                        return $this->render('reappro/createReappro.html.twig', [
                            'form' => $form
                        ]);
                    }
                    $Demande->setIdReappro(25);
                    $Demande->setidPreparateur($userId);
                    $Demande->setUsernamePrep($username);
                    $Demande->setSonPicking('NON');
                    $Demande->setStatut('A');
                    $Demande->setCreateAt(new \DateTimeImmutable());
                    $Demande->setAdresse($adresseConvertie);
                    $em->persist($Demande);
                    $em->flush();
            
                    $this->addFlash('success', 'La demande est bien envoyée');
                    return $this->redirectToRoute('add_reappro');
        }
        

            }

       

       
        

        return $this->render('reappro/createReappro.html.twig', [
            'form' => $form
        ]);
    }
    #[Route('/adduser', name: 'adduser_reappro')]
    public function adduser(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, DemandeReapproRepository $repositoryReappro): Response
    {
        $user = new User();
        $user->setEmail('emmanuelc@doe.fr')
            ->setUsername('emmanuelc')
            ->setPassword($hasher->hashPassword($user,'1021'))
            ->setRoles([]);
        $em->persist($user);
        $em->flush();
        return $this-render('reappro/index.html.twig');

    }
    
}
