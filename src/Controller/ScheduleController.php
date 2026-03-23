<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Schedule;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ScheduleRepository;
use App\Form\ScheduleFormType;

final class ScheduleController extends AbstractController
{
    #[Route('/schedule', name: 'app_schedule')]
    public function index(ScheduleRepository $scheduleRepository): Response
    {
        $allSchedules = $scheduleRepository->findBy(
            ['deleted_at' => null],
            ['dayOfWeek' => 'ASC', 'startTime' => 'ASC']
        );

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
        $form = $this->createForm(ScheduleFormType::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $startTime = $schedule->getStartTime();
            $endTime = $schedule->getEndTime();
            $day = $schedule->getDayOfWeek();
            $period = $schedule->getPeriod();

            // 1. Vérif Heure début < Heure fin
            if ($startTime >= $endTime) {
                $this->addFlash('error', 'L\'heure de début doit être antérieure à l\'heure de fin.');
            }
            // 2. heure max pour le matin limité à 13h00
            elseif ($period === 'Matin' && $endTime->format('H:i') > '13:00') {
                $this->addFlash('error', 'Le matin ne peut pas se terminer après 13h00.');
            }
            else {
                $repo = $entityManager->getRepository(Schedule::class);

                // 3. Vérif Doublon de Période
                $duplicate = $repo->findOneBy(['dayOfWeek' => $day, 'period' => $period, 'deleted_at' => null]);

                if ($duplicate) {
                    $this->addFlash('error', "Le créneau du $period pour ce jour existe déjà.");
                } else {
                    $hasError = false;
                    // 4. Validation croisée
                    if ($period === 'Après-midi') {
                        $morning = $repo->findOneBy(['dayOfWeek' => $day, 'period' => 'Matin', 'deleted_at' => null]);
                        if ($morning && $startTime < $morning->getEndTime()) {
                            $this->addFlash('error', 'L\'après-midi doit commencer après le matin.');
                            $hasError = true;
                        }
                    } else {
                        $afternoon = $repo->findOneBy(['dayOfWeek' => $day, 'period' => 'Après-midi', 'deleted_at' => null]);
                        if ($afternoon && $endTime > $afternoon->getStartTime()) {
                            $this->addFlash('error', 'Le matin doit finir avant l\'après-midi.');
                            $hasError = true;
                        }
                    }

                    if (!$hasError) {
                        $entityManager->persist($schedule);
                        $entityManager->flush();
                        $this->addFlash('success', 'Créneau ajouté !');
                        return $this->redirectToRoute('app_schedule');
                    }
                }
            }
        }

        return $this->render('schedule/schedule_add.html.twig', [
            'ScheduleFormType' => $form->createView(),
        ]);
    }

    #[Route('/schedule/edit/{id}', name: 'app_schedule_edit')]
    public function editSchedule(Request $request, EntityManagerInterface $entityManager, Schedule $schedule): Response
    {
        $form = $this->createForm(ScheduleFormType::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $startTime = $schedule->getStartTime();
            $endTime = $schedule->getEndTime();
            $day = $schedule->getDayOfWeek();
            $period = $schedule->getPeriod();

            if ($startTime >= $endTime) {
                $this->addFlash('error', 'L\'heure de début doit être antérieure à l\'heure de fin.');
            }
            elseif ($period === 'Matin' && $endTime->format('H:i') > '13:00') {
                $this->addFlash('error', 'Le matin ne peut pas se terminer après 13h00.');
            }
            else {
                $repo = $entityManager->getRepository(Schedule::class);
                $duplicate = $repo->findOneBy(['dayOfWeek' => $day, 'period' => $period, 'deleted_at' => null]);

                if ($duplicate && $duplicate->getId() !== $schedule->getId()) {
                    $this->addFlash('error', "Le créneau du $period pour ce jour existe déjà.");
                } else {
                    $hasError = false;
                    if ($period === 'Après-midi') {
                        $morning = $repo->findOneBy(['dayOfWeek' => $day, 'period' => 'Matin', 'deleted_at' => null]);
                        if ($morning && $startTime < $morning->getEndTime()) {
                            $this->addFlash('error', 'L\'après-midi doit commencer après le matin.');
                            $hasError = true;
                        }
                    } else {
                        $afternoon = $repo->findOneBy(['dayOfWeek' => $day, 'period' => 'Après-midi', 'deleted_at' => null]);
                        if ($afternoon && $endTime > $afternoon->getStartTime()) {
                            $this->addFlash('error', 'Le matin doit finir avant l\'après-midi.');
                            $hasError = true;
                        }
                    }

                    if (!$hasError) {
                        $entityManager->flush();
                        $this->addFlash('success', 'Modifié !');
                        return $this->redirectToRoute('app_schedule');
                    }
                }
            }
        }

        return $this->render('schedule/schedule_edit.html.twig', [
            'ScheduleFormType' => $form->createView(),
            'schedule' => $schedule,
        ]);
    }

    #[Route('/schedule/delete/{id}', name: 'app_schedule_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, Schedule $schedule): Response
    {
        if ($this->isCsrfTokenValid('delete' . $schedule->getId(), $request->request->get('_token'))) {
            $schedule->setDeletedAt(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Créneau supprimé avec succès !');
        } else {
            $this->addFlash('error', 'Token invalide.');
        }

        return $this->redirectToRoute('app_schedule');
    }
}
