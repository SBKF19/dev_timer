<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\UserRepository;

#[Route('/user', name: 'user_')]
class UserController extends AbstractController
{
    #[Route('/', name: 'list')]
    public function list(UserRepository $userRepository, Request $request): Response
    {
        $filters = [
            'search' => $request->query->get('search'),
            'status' => $request->query->get('status'),
            'user_id' => $request->query->get('user_id'),
        ];

        if (!empty($filters['user_id'])) {
            $users = [$userRepository->find($filters['user_id'])];
        } else {
            $users = $userRepository->findByFilters($filters);
        }
        
        return $this->render('user/index.html.twig', [
            'users' => $users,
            'allUsers' => $userRepository->findBy([], ['lastname' => 'ASC']),
            'filters' => $filters
        ]);        
    }

    #[Route('/add', name: 'add')]
    public function add(UserRepository $userRepository, Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userRepository->save($user, true);

            $this->addFlash('success', 'Utilisateur ajouter avec succès !');

            return $this->redirectToRoute('user_list');
        }

        return $this->render('user/form.html.twig', [
            'form' => $form->createView(),
            'editMode' => false,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
    
    #[Route('/edit/{id}', name: 'edit')]
    public function edit(User $user, Request $request, UserRepository $userRepository): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userRepository->save($user, true);

            $this->addFlash('success', 'Utilisateur modifié avec succès !');

            return $this->redirectToRoute('user_list');
        }

        return $this->render('user/form.html.twig', [
            'form' => $form->createView(),
            'editMode' => true,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }


    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(User $user, Request $request, UserRepository $userRepository): Response
    {
        // On vérifie le jeton CSRF pour la sécurité
        $token = $request->request->get('_token');
    
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $token)) {
            
            $user->setDeletedAt(new \DateTime());
        
            $user->setStatus(false);

            $userRepository->save($user, true);

            $this->addFlash('success', 'Utilisateur supprimé (archivé) avec succès.');
        }

        return $this->redirectToRoute('user_list');
    }
}