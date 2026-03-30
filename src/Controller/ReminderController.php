<?php
namespace App\Controller;

use App\Entity\Reminder;
use App\Form\ReminderType;
use App\Repository\ReminderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reminder')]
class ReminderController extends AbstractController
{
    #[Route('/', name: 'app_reminder_index', methods: ['GET'])]
    public function index(ReminderRepository $reminderRepository): Response
    {
        return $this->render('reminder/list.html.twig', [
            'reminders' => $reminderRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_reminder_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ReminderRepository $repo): Response
    {
        $reminder = new Reminder();
        $form = $this->createForm(ReminderType::class, $reminder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($reminder->isActive()) {
                $this->handleExclusiveActivation($reminder, $repo);
            }
            $entityManager->persist($reminder);
            $entityManager->flush();
            return $this->redirectToRoute('app_reminder_index');
        }

        return $this->render('reminder/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reminder_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reminder $reminder, EntityManagerInterface $entityManager, ReminderRepository $repo): Response
    {
        $form = $this->createForm(ReminderType::class, $reminder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($reminder->isActive()) {
                $this->handleExclusiveActivation($reminder, $repo);
            }
            $entityManager->flush();
            return $this->redirectToRoute('app_reminder_index');
        }

        return $this->render('reminder/edit.html.twig', [
            'form' => $form->createView(),
            'reminder' => $reminder
        ]);
    }

    #[Route('/{id}/activate', name: 'app_reminder_activate', methods: ['POST'])]
    public function activate(Reminder $reminder, ReminderRepository $repo, EntityManagerInterface $em): Response
    {
        $this->handleExclusiveActivation($reminder, $repo);
        $reminder->setIsActive(true);
        $em->flush();

        return $this->redirectToRoute('app_reminder_index');
    }

    private function handleExclusiveActivation(Reminder $activeReminder, ReminderRepository $repo): void
    {
        $all = $repo->findAll();
        foreach ($all as $r) {
            if ($r !== $activeReminder) {
                $r->setIsActive(false);
            }
        }
    }
}