<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Schedule;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ScheduleRepository;
use Knp\Component\Pager\PaginatorInterface;
use App\Form\ScheduleForm;

final class ScheduleController extends AbstractController
{
    #[Route('/schedule', name: 'app_schedule')]
    public function index(ScheduleRepository $scheduleRepository): Response
    {
        // On récupère tous les horaires triés par jour
        $allSchedules = $scheduleRepository->findBy([], ['dayOfWeek' => 'ASC', 'startTime' => 'ASC']);

        $groupedSchedules = [];
        foreach ($allSchedules as $schedule) {
        // 1. On récupère le numéro du jour (ex: 1)
        $day = $schedule->getDayOfWeek();

        // 2. pour chaque jour on créer une liste si pas encore définie
        if (!isset($groupedSchedules[$day])) {
            $groupedSchedules[$day] = [];
        }

        // 3. On ajoute l'horaire dans la liste
        array_push($groupedSchedules[$day], $schedule);
        }

        return $this->render('schedule/schedule_list.html.twig', [
            'schedules' => $groupedSchedules,
        ]);
    }

    #[Route('/schedule/add', name: 'app_schedule_add')]
    public function addSchedule(Request $request, EntityManagerInterface $entityManager, ScheduleRepository $scheduleRepository): Response
    {
        $schedule = new Schedule();
        $form = $this->createForm(ScheduleForm::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $startTime = $schedule->getStartTime();
            $endTime = $schedule->getEndTime();

            // vérification que l'heure de fin de n'est pas antérieure à l'heure début

            if ($startTime >= $endTime) {
                $this->addFlash('error', 'L\'heure de début doit être antérieure à l\'heure de fin.');

                } else {
                // On vérifie qu'il n'existe pas déjà un créneau pour ce jour et cette plage horaire
                $existingSchedule = $entityManager->getRepository(Schedule::class)->findOneBy([
                    'dayOfWeek' => $schedule->getDayOfWeek(),
                    'startTime' => $startTime,
                    'endTime'   => $endTime
                ]);

                if ($existingSchedule) {
                    $this->addFlash('error', 'Un créneau horaire existe déjà pour cette période.');

                } else {

                    $entityManager->persist($schedule);
                    $entityManager->flush();
                    $this->addFlash('success', 'Créneau horaire ajouté avec succès !');
                    $schedule = new Schedule();
                    $form = $this->createForm(ScheduleForm::class, $schedule);


                }
            }
        }

        return $this->render('schedule/schedule_add.html.twig', [
            'scheduleForm' => $form->createView(),
        ]);
    }


    #[Route('/schedule/edit/{id}', name: 'app_schedule_edit')]
    public function editSchedule(Request $request, EntityManagerInterface $entityManager, ScheduleRepository $scheduleRepository, int $id): Response
    {
        $schedule = $scheduleRepository->find($id);

        if (!$schedule) {
            $this->addFlash('error', 'Créneau introuvable.');
            return $this->redirectToRoute('app_schedule');
        }

        $form = $this->createForm(ScheduleForm::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $startTime = $schedule->getStartTime();
            $endTime = $schedule->getEndTime();

            if ($startTime >= $endTime) {
                $this->addFlash('error', 'L\'heure de début doit être antérieure à l\'heure de fin.');
            } else {
                $existingSchedule = $entityManager->getRepository(Schedule::class)->findOneBy([
                    'dayOfWeek' => $schedule->getDayOfWeek(),
                    'startTime' => $startTime,
                    'endTime'   => $endTime
                ]);

                if ($existingSchedule && $existingSchedule->getId() !== $schedule->getId()) {
                    $this->addFlash('error', 'Un créneau horaire existe déjà pour cette période.');
                } else {
                    $entityManager->flush();
                    $this->addFlash('success', 'Créneau horaire modifié avec succès !');
                    return $this->redirectToRoute('app_schedule');
                }
            }
        }
        return $this->render('schedule/schedule_edit.html.twig', [
            'scheduleForm' => $form->createView(),
            'schedule' => $schedule,
        ]);
    }

    #[Route('/schedule/delete/{id}', name: 'app_schedule_delete', methods: ['POST'])]
    public function delete(Request $request, Schedule $schedule, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete_schedule', $request->request->get('_token'))) {
            $entityManager->remove($schedule);
            $entityManager->flush();
            $this->addFlash('success', 'Créneau horaire supprimé !');
        }

        return $this->redirectToRoute('app_schedule');
    }



}
