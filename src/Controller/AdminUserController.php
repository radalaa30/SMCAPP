<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/users')]
class AdminUserController extends AbstractController
{
    #[Route('', name: 'admin_users_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $repo): Response
    {
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = min(100, max(5, (int) $request->query->get('limit', 20)));
        $search = trim((string) $request->query->get('q', ''));

        [$items, $total] = $repo->findPaginated($page, $limit, $search);

        return $this->render('admin/user/index.html.twig', [
            'users'      => $items,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'pages'      => (int) ceil($total / $limit),
            'search'     => $search,
        ]);
    }

    #[Route('/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $repo
    ): Response {
        $user = new User();

        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string|null $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            // Si tu veux forcer ROLE_USER implicite, inutile de l’ajouter ici : getRoles() l’ajoute déjà à l’affichage.
            $repo->save($user);

            $this->addFlash('success', 'Utilisateur créé avec succès.');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $repo
    ): Response {
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string|null $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            if (!empty($plainPassword)) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $repo->save($user);

            $this->addFlash('success', 'Utilisateur mis à jour avec succès.');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, UserRepository $repo): Response
    {
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete_user_' . $user->getId(), $token)) {
            // Empêche la suppression de soi-même (conseillé)
            if ($this->getUser() instanceof User && $this->getUser()->getId() === $user->getId()) {
                $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            } else {
                $repo->remove($user);
                $this->addFlash('success', 'Utilisateur supprimé avec succès.');
            }
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('admin_users_index');
    }
}
