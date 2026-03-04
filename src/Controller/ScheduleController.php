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
    public function index(ScheduleRepository $scheduleRepository, PaginatorInterface $paginator, Request $request): Response
    {
        $qb = $scheduleRepository->findAll();
        $page = $request->query->getInt('page', 1);
        $limit = 10;

        $pagination = $paginator->paginate(
            $qb,
            $page,
            $limit
        );

        return $this->render('schedule/schedule_list.html.twig', [
            'pagination' => $pagination,
        ]);
    }
}
