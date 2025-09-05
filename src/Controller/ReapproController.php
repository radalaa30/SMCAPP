<?php
namespace App\Controller;

use App\Entity\User;
use App\Entity\DemandeReappro;
use App\Entity\Blencours;
use App\Entity\Suividupreparationdujour;
use App\Form\ValidationType;
use App\Repository\DemandeReapproRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ReapproController extends AbstractController
{
#[Route('/', name: 'app_reappro')]
public function index(
    Request $request,
    EntityManagerInterface $em,
    DemandeReapproRepository $repositoryReappro
): Response {
    $this->denyAccessUnlessGranted('ROLE_USER');

    $user = $this->getUser();
    if (!$user) {
        throw $this->createAccessDeniedException('User not authenticated.');
    }

    $userId = $user->getId();
    $username = $user->getUsername();

    
    // Recherche selon différents critères (dans l'ordre de priorité)
    $demandeUrgent = $repositoryReappro->findOldestByStatusExcludingVAndEBYIDcariste($userId)
        ?? $repositoryReappro->findOldestByStatusExcludingVAndE($userId)
        ?? $repositoryReappro->findOldestByStatusA($userId, $username);
    
    if ($demandeUrgent) {
        return $this->render('reappro/index.html.twig', [
            'results' => [$demandeUrgent]
        ]);
    }
    // Recherche selon différents critères (dans l'ordre de priorité)
            $demandeCold = $repositoryReappro->findOldestByStatusExcludingVAndEBYIDcaristecold($userId)
                ?? $repositoryReappro->findOldestByStatusExcludingVAndE($userId)
                ?? $repositoryReappro->findOldestByStatusAcold($userId, $username);
                

            if ($demandeCold) {
                return $this->render('reappro/index.html.twig', [
                    'results' => [$demandeCold]
                ]);
            }
   
        
    // Nouvelle logique : recherche dans Blencours et Suividupreparationdujour
    $blEnCours = $em->getRepository(Blencours::class)->findAll();


       


    // Variable pour indiquer si une demande a été créée
    $demandesCreees = false;

    if($blEnCours){
        foreach ($blEnCours as $bl) {
            $numBl = $bl->getNumBl();
            $Pickingnok = 0;
            $Pickingok = 0;

           
           
            $suiviPreparations = $em->getRepository(Suividupreparationdujour::class)
                ->findByNoBLWithSpecificAddresses($numBl);
               
                
            foreach ($suiviPreparations as $suivi) {
                // Vérifiez d'abord la valeur exacte du code produit
                $codeproduit = trim($suivi->getCodeProduit());
                $zone = trim($suivi->getZone());
                $adresse = trim($suivi->getAdresse());
                $resultatAdresse = $zone . ":" . $adresse;

                
                //var_dump($suiviPreparations);
            

                // Verfifier si la demande(adresse) n'existe pas dans les demande de reappro 
                $demandeExistante = $em->getRepository(DemandeReappro::class)
                    ->findOneBy(['Adresse' => $resultatAdresse]);
          
                   
                    
                if(empty($demandeExistante)){
                         
                    // Chercher si la ref a un piking ou non
                    if($Pickingok == 0 && $Pickingnok == 0){
                        // Verfifier si Picking Ok
                        
                        $SonPiking = 1;
                        $listeProduit = $em->getRepository('App\Entity\ListeProduits')
                            ->createQueryBuilder('p')
                            ->where('p.ref = :ref')
                            ->andWhere('p.pinkg IS NOT NULL')
                            ->andWhere('p.pinkg <> :empty')
                            ->setParameter('ref', $codeproduit)
                            ->setParameter('empty', '')
                            ->getQuery()
                            ->getOneOrNullResult();
                            
                        if($listeProduit){
                            // Créer une nouvelle demande de réappro
                            $demandeReappro = new DemandeReappro();
                            $demandeReappro->setIdReappro(9999999);
                            $demandeReappro->setIdPreparateur(500100);
                            $demandeReappro->setAdresse($resultatAdresse);
                            $demandeReappro->setStatut('A');
                            $demandeReappro->setCreateAt(new \DateTimeImmutable());
                            $demandeReappro->setUsernamePrep('Cold');
                            $demandeReappro->setSonPicking($SonPiking);
                            $em->persist($demandeReappro);
                            $em->flush(); 
                            
                            $demandesCreees = true;
                        }
                    }
                    
                    if($Pickingok == 1 && $Pickingnok == 1){
                        $listeProduit = $em->getRepository('App\Entity\ListeProduits')
                            ->createQueryBuilder('p')
                            ->where('p.ref = :ref')
                            ->setParameter('ref', $codeproduit)
                            ->getQuery()
                            ->getOneOrNullResult();
                            
                        // Ensuite, vous pouvez accéder à la valeur de pinkg, qu'elle soit null ou non
                        $pickingValue = ($listeProduit) ? $listeProduit->getPinkg() : null;
                        if($pickingValue){
                            $SonPiking = 1;
                        } else {
                            $SonPiking = 0;
                        }
                       
                        // Créer une nouvelle demande de réappro
                        $demandeReappro = new DemandeReappro();
                        $demandeReappro->setIdReappro(9999999);
                        $demandeReappro->setIdPreparateur(500100);
                        $demandeReappro->setAdresse($resultatAdresse);
                        $demandeReappro->setStatut('A');
                        $demandeReappro->setCreateAt(new \DateTimeImmutable());
                        $demandeReappro->setUsernamePrep('Cold');
                        $demandeReappro->setSonPicking($SonPiking);
                        $em->persist($demandeReappro);
                        $em->flush();
                        
                        $demandesCreees = true;
                    }
                }
            }
            
        }
   
        // Une fois que tous les BLs ont été traités, on cherche les demandes
       // if ($demandesCreees) {
            
            // Recherche selon différents critères (dans l'ordre de priorité)
            $demandeCold = $repositoryReappro->findOldestByStatusExcludingVAndEBYIDcaristecold($userId)
                ?? $repositoryReappro->findOldestByStatusExcludingVAndE($userId)
                ?? $repositoryReappro->findOldestByStatusAcold($userId, $username);
                

            if ($demandeCold) {
                return $this->render('reappro/index.html.twig', [
                    'results' => [$demandeCold]
                ]);
            }
       // }
    }
    
    // Si aucune donnée n'est trouvée
    $this->addFlash('notice', 'Aucune palette');
    return $this->render('reappro/index.html.twig', [
        'results' => []
    ]);
}
    
    #[Route('/validation/{id}', name: 'edit_validation', requirements: ['id' => '\d+'])]
    public function editReappro(
        Request $request,
        DemandeReappro $listeDemande,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
       
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('User not authenticated.');
        }
        
        $form = $this->createForm(ValidationType::class, $listeDemande);
        $form->handleRequest($request);
        
        // Récupération et normalisation des adresses
        $adresseForm = $form->get('adresse')->getData();
        $adresseConvertie = preg_replace('/^10/', 'C', $adresseForm ?? '');
        $adresseDistParam = $request->query->get('adresseDist', '');
        $adresseDistConvertie = preg_replace('/^10/', 'C', $adresseDistParam);
        
        // Vérification que l'adresse de distribution ne commence pas déjà par 'C'
        if (strpos($adresseDistParam, 'C') === 0) {
            $this->addFlash('error', 'L\'adresse est incorrecte');
            return $this->redirectToRoute('app_reappro');
        }
        
        // Vérification de la correspondance des adresses
        if ($adresseConvertie !== $adresseDistConvertie) {
            $this->addFlash('error', 'L\'adresse est incorrecte');
            return $this->redirectToRoute('app_reappro');
        }
        
        if ($form->isSubmitted() && $form->isValid()) {
            $listeDemande->setIdCariste($user->getId());
            $listeDemande->setUsernameCariste($user->getUsername());
            $listeDemande->setSonPicking('NON');
            $listeDemande->setUpdateAt(new \DateTimeImmutable());
            $listeDemande->setStatut('V');
            
            $em->flush();
            
            $this->addFlash('success', 'La demande est bien envoyée');
            return $this->redirectToRoute('app_reappro');
        }
        
        return $this->render('reappro/Validation.html.twig', [
            'form' => $form
        ]);
    }
    
    #[Route('/add-rea', name: 'add_reappro')]
    public function addReappro(
        Request $request,
        EntityManagerInterface $em,
        DemandeReapproRepository $repositoryReappro
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('User not authenticated.');
        }
        
        $demande = new DemandeReappro();
        $form = $this->createForm(ValidationType::class, $demande);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $adresseForm = $form->get('adresse')->getData();
            $adresseConvertie = preg_replace('/^10/', 'C', $adresseForm ?? '');
            
            // Si l'adresse est vide
            if (empty($adresseConvertie)) {
                $this->addFlash('warning', 'La demande est vide');
                return $this->render('reappro/createReappro.html.twig', [
                    'form' => $form
                ]);
            }
            
            // Vérifier si une demande existe déjà pour cette adresse
            $existingDemande = $repositoryReappro->findExistingDemandeByAdresse($adresseConvertie);
            

          // dd($existingDemande);
            
       


      
           
            
            if ($existingDemande) {

                   $nameprep =$existingDemande->getUsernamePrep();
        $idreapp = $existingDemande->getId();
            // Récupérer l'objet par son ID (65 dans votre exemple)
            $demande = $em->getRepository(DemandeReappro::class)->find($idreapp);

            // Vérifier si l'objet existe et si le UsernamePrep est "Cold"
            if ($demande && $demande->getUsernamePrep() === 'Cold') {
                // Mettre à jour le nom d'utilisateur
                $demande->setUsernamePrep($user->getUsername().'//Cold');
                $demande->setCreateAt(new \DateTimeImmutable());
                $em->persist($demande);
                $em->flush();
            }
            
               
               
                $this->addFlash(
                    'warning',
                    sprintf(
                        'Palette est déjà en cours de déplacement pour l\'adresse %s (statut: %s)',
                        $existingDemande->getAdresse(),
                        $existingDemande->getStatut()
                    )
                );
                return $this->render('reappro/createReappro.html.twig', [
                    'form' => $form
                ]);
            }
            
            // Création de la nouvelle demande
            $demande->setIdReappro(mt_rand(10000, 99999)); // Génération d'un ID aléatoire
            $demande->setIdPreparateur($user->getId());
            $demande->setUsernamePrep($user->getUsername());
            $demande->setSonPicking('NON');
            $demande->setStatut('A');
            $demande->setCreateAt(new \DateTimeImmutable());
            $demande->setAdresse($adresseConvertie);
            
            $em->persist($demande);
            $em->flush();
            
            $this->addFlash('success', 'La demande est bien envoyée');
            
            // Redirection vers la création d'une nouvelle demande avec un formulaire vide
            return $this->redirectToRoute('add_reappro');
        }
        
        return $this->render('reappro/createReappro.html.twig', [
            'form' => $form
        ]);
    }
    
    #[Route('/adduser', name: 'adduser_reappro')]
    public function adduser(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = new User();
        $user->setEmail('emmanuelc@doe.fr')
            ->setUsername('emmanuelc')
            ->setPassword($hasher->hashPassword($user, '1021'))
            ->setRoles(['ROLE_USER']);
            
        $em->persist($user);
        $em->flush();
        
        $this->addFlash('success', 'Utilisateur créé avec succès');
        
        return $this->redirectToRoute('app_reappro');
    }
}