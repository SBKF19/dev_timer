<?php

namespace App\Controller;

use App\Entity\Project;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
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

        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                if ($form->get('archived_at')->getData() === false) {
                    $project->setArchivedAt(new \DateTimeImmutable());
                }

                $user = $this->getUser();
                $project->setCreatedBy($user);

                $entityManager->persist($project);
                $entityManager->flush();

                $this->addFlash('success', 'Le projet a été créé avec succès !');
                return $this->redirectToRoute('app_project'); // Assure-toi que cette route existe
            }
        }

        return $this->render('project/form.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/project/edit/{id}', name: 'app_project_edit')]
    public function edit(Project $project, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $isActif = $form->get('archived_at')->getData();
                $currentArchivedAt = $project->getArchivedAt();

                if ($isActif === true && $currentArchivedAt !== null) {
                    $project->setArchivedAt(null);
                } elseif ($isActif === false && $currentArchivedAt === null) {
                    $project->setArchivedAt(new \DateTimeImmutable());
                }

                $this->addFlash('success', 'Le projet a été mis à jour.');
                $em->flush();
                return $this->redirectToRoute('app_project');
            }
        }

        return $this->render('project/form.html.twig', [
            'form' => $form->createView(),
            'project' => $project,
            'isEdit' => true
        ]);
    }

    #[Route('/project/delete/{id}', name: 'app_project_delete', methods: ['POST'])]
    public function delete(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        // Vérification du token CSRF pour empêcher les failles de sécurité
        if ($this->isCsrfTokenValid('delete' . $project->getId(), $request->request->get('_token'))) {

            if (!$project->getHourEntries()->isEmpty()) {
                $this->addFlash('error', 'Impossible de supprimer ce projet : des heures y sont déjà rattachées.');
                return $this->redirectToRoute('app_project');
            }
            $em->remove($project);
            $em->flush();

            $this->addFlash('success', 'Le projet a été supprimé avec succès.');
            return $this->redirectToRoute('app_project');
        }

        return $this->redirectToRoute('app_project_new');
    }

    #[Route('/projects', name: 'app_project', methods: ['GET', 'POST'])]
    public function list(ProjectRepository $projectRepo, Request $request): Response
    {
        $projects = $projectRepo->findAll();

        return $this->render('project/list.html.twig', [
            'projects' => $projects
        ]);
    }
}
// textarea commentaire, couleur et heure total projet dans le tableau, 