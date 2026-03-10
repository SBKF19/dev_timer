<?php

namespace App\Controller;

use App\Entity\Project;
use App\Form\ProjectType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProjectController extends AbstractController
{
    #[Route('/project/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $project = new Project();

        // On crée le formulaire en lui passant l'instance de notre entité
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Optionnel : Si 'archived_at' est une date dans ton entité mais un checkbox dans le form,
                // tu devrais gérer la logique ici. Exemple :
                if ($form->get('archived_at')->getData() === false) {
                    $project->setArchivedAt(new \DateTimeImmutable());
                }
                $user = $this->getUser();
                $project->setCreatedBy($user);

                $entityManager->persist($project);
                $entityManager->flush();

                $this->addFlash('success', 'Le projet a été créé avec succès !');

                return $this->redirectToRoute('app_project_new'); // Assure-toi que cette route existe
            }
        }

        return $this->render('project/new.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }
}