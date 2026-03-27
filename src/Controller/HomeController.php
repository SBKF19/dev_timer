<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\TimeStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(
        UserRepository $userRepo,
        TimeStatsService $timeStatsService,
        ChartBuilderInterface $chartBuilder
    ): Response {
        //DATES : MOIS EN COURS ---
        $startDate = new \DateTime('first day of this month 00:00:00');
        $endDate = new \DateTime('last day of this month 23:59:59');

        // Récupération des utilisateurs actifs
        $users = $userRepo->findBy(['status' => true]);

        //  GRAPHIQUE PAR PROJET ---
        $projectRawData = $timeStatsService->getProjectChartRawData($users, $startDate, $endDate);
        $projectChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $projectChart->setData([
            'labels' => $projectRawData['labels'],
            'datasets' => $projectRawData['datasets'],
        ]);
        $projectChart->setOptions($this->getDefaultOptions('Heures par projet'));

        // GRAPHIQUE PAR ACTIVITÉ ---
        $activityRawData = $timeStatsService->getActivityChartRawData($users, $startDate, $endDate);
        $activityChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $activityChart->setData([
            'labels' => $activityRawData['labels'],
            'datasets' => $activityRawData['datasets'],
        ]);
        $activityChart->setOptions($this->getDefaultOptions('Heures par activité'));
        $completionStats = $timeStatsService->getCompletionStats($this->getUser());

        return $this->render('home/home.html.twig', [
            'projectChart' => $projectChart,
            'activityChart' => $activityChart,
            'monthName' => $startDate->format('F Y') ,
            'completionStats' => $completionStats,
        ]);
    }

    /**
     * Configuration commune pour éviter la répétition
     */
    private function getDefaultOptions(string $title): array
    {
        return [
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => ['stacked' => true],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => $title]
                ],
            ],
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ];
    }
}