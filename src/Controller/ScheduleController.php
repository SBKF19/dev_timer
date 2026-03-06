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
}
