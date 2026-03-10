<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Schedule;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ScheduleRepository;
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
            $day = $schedule->getDayOfWeek();

            if (!isset($groupedSchedules[$day])) {
                $groupedSchedules[$day] = [];
            }

            $groupedSchedules[$day][] = $schedule;
        }

        return $this->render('schedule/schedule_list.html.twig', [
            'schedules' => $groupedSchedules,
        ]);
    }

    #[Route('/schedule/add', name: 'app_schedule_add')]
    public function addSchedule(Request $request, EntityManagerInterface $entityManager): Response
    {
        $schedule = new Schedule();
        $form = $this->createForm(ScheduleForm::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $startTime = $schedule->getStartTime();
            $endTime = $schedule->getEndTime();

            // 1. Vérification Heure début < Heure fin
            if ($startTime >= $endTime) {
                $this->addFlash('error', 'L\'heure de début doit être antérieure à l\'heure de fin.');
            } else {
                // 2. Vérification doublon exact
                $existingSchedule = $entityManager->getRepository(Schedule::class)->findOneBy([
                    'dayOfWeek' => $schedule->getDayOfWeek(),
                    'period'    => $schedule->getPeriod(),
                    'startTime' => $startTime,
                    'endTime'   => $endTime
                ]);

                if ($existingSchedule) {
                    $this->addFlash('error', 'Un créneau horaire existe déjà pour cette période.');
                } else {
                    $hasError = false;

                    // 3. Validation croisée Matin / Après-midi
                    if ($schedule->getPeriod() === 'Après-midi') {
                        $morningSchedule = $entityManager->getRepository(Schedule::class)->findOneBy([
                            'dayOfWeek' => $schedule->getDayOfWeek(),
                            'period'    => 'Matin'
                        ]);
                        if ($morningSchedule && $startTime < $morningSchedule->getEndTime()) {
                            $this->addFlash('error', 'L\'heure de début de l\'après-midi doit commencer après la fin du matin.');
                            $hasError = true;
                        }
                    } else {
                        $afternoonSchedule = $entityManager->getRepository(Schedule::class)->findOneBy([
                            'dayOfWeek' => $schedule->getDayOfWeek(),
                            'period'    => 'Après-midi'
                        ]);
                        if ($afternoonSchedule && $endTime > $afternoonSchedule->getStartTime()) {
                            $this->addFlash('error', 'L\'heure de fin du matin doit se terminer avant le début de l\'après-midi.');
                            $hasError = true;
                        }
                    }

                    // 4. Persistance si tout est OK
                    if (!$hasError) {
                        $entityManager->persist($schedule);
                        $entityManager->flush();
                        $this->addFlash('success', 'Créneau horaire ajouté avec succès !');
                        return $this->redirectToRoute('app_schedule');
                    }
                }
            }
        }

        return $this->render('schedule/schedule_add.html.twig', [
            'scheduleForm' => $form->createView(),
        ]);
    }

    #[Route('/schedule/edit/{id}', name: 'app_schedule_edit')]
    public function editSchedule(Request $request, EntityManagerInterface $entityManager, Schedule $schedule): Response
    {
        $form = $this->createForm(ScheduleForm::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $startTime = $schedule->getStartTime();
            $endTime = $schedule->getEndTime();

            // 1. Vérification de base : début < fin
            if ($startTime >= $endTime) {
                $this->addFlash('error', 'L\'heure de début doit être antérieure à l\'heure de fin.');
            } else {
                $hasError = false;

                // 2. Vérification du chevauchement avec l'AUTRE période du même jour
                if ($schedule->getPeriod() === 'Après-midi') {
                    $morningSchedule = $entityManager->getRepository(Schedule::class)->findOneBy([
                        'dayOfWeek' => $schedule->getDayOfWeek(),
                        'period'    => 'Matin'
                    ]);

                    // Si on modifie l'après-midi, son début ne doit pas être AVANT la fin du matin
                    if ($morningSchedule && $startTime < $morningSchedule->getEndTime()) {
                        $this->addFlash('error', 'L\'après-midi doit commencer après la fin du matin (' . $morningSchedule->getEndTime()->format('H:i') . ').');
                        $hasError = true;
                    }
                } else {
                    $afternoonSchedule = $entityManager->getRepository(Schedule::class)->findOneBy([
                        'dayOfWeek' => $schedule->getDayOfWeek(),
                        'period'    => 'Après-midi'
                    ]);

                    // Si on modifie le matin, sa fin ne doit pas être APRÈS le début de l'après-midi
                    if ($afternoonSchedule && $endTime > $afternoonSchedule->getStartTime()) {
                        $this->addFlash('error', 'Le matin doit se terminer avant le début de l\'après-midi (' . $afternoonSchedule->getStartTime()->format('H:i') . ').');
                        $hasError = true;
                    }
                }

                // 3. Sauvegarde si aucune erreur de logique
                if (!$hasError) {
                    $entityManager->flush();
                    $this->addFlash('success', 'Créneau horaire modifié avec succès !');
                    return $this->redirectToRoute('app_schedule');
                }
            }
        }

        return $this->render('schedule/schedule_edit.html.twig', [
            'scheduleForm' => $form->createView(),
            'schedule'     => $schedule,
        ]);
    }
    #[Route('/schedule/delete/{id}', name: 'app_schedule_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, Schedule $schedule): Response
    {
        if ($this->isCsrfTokenValid('delete_schedule', $request->request->get('_token'))) {
            $entityManager->remove($schedule);
            $entityManager->flush();
            $this->addFlash('success', 'Créneau horaire supprimé !');
        }

        return $this->redirectToRoute('app_schedule');
    }
}
