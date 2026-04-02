<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\TimeStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(
        Request $request,
        UserRepository $userRepo,
        TimeStatsService $timeStatsService,
        ChartBuilderInterface $chartBuilder
    ): Response {
        $today = new \DateTime();
        $period = $request->query->get('period');
        $startDateStr = $request->query->get('start_date');
        $endDateStr = $request->query->get('end_date');

        // --- 1. GESTION DES DATES ---
        if ($startDateStr || $endDateStr) {
            // Si des dates sont saisies manuellement dans le formulaire
            $startDate = $startDateStr ? new \DateTime($startDateStr) : (clone $today)->modify('first day of this month');
            $endDate = $endDateStr ? new \DateTime($endDateStr) : (clone $today)->modify('last day of this month');
            $period = 'custom'; 
        } elseif ($period === 'week') {
            $startDate = (clone $today)->modify('Monday this week')->setTime(0, 0, 0);
            $endDate = (clone $today)->modify('Sunday this week')->setTime(23, 59, 59);
        } elseif ($period === 'year') {
            $startDate = (clone $today)->modify('first day of January this year')->setTime(0, 0, 0);
            $endDate = (clone $today)->modify('last day of December this year')->setTime(23, 59, 59);
        } else {
        
        // Récupération depuis le formulaire (Filtre personnalisé)
        // Si pas de dates saisies, on met par défaut la semaine en cours

            $startDate = (clone $today)->modify('first day of this month')->setTime(0, 0, 0);
            $endDate = (clone $today)->modify('last day of this month')->setTime(23, 59, 59);
            $period = $period ?: 'month';
        }

        // Sécurité : on force l'heure de fin à la dernière seconde de la journée
        $endDate->setTime(23, 59, 59);
        
        // Graphique par Projet
        // On passe un tableau [$this->getUser()] pour la compatibilité avec le service
        $projectRawData = $timeStatsService->getProjectChartRawData([$this->getUser()], $startDate, $endDate);
        $projectChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $projectChart->setData([
            'labels' => $projectRawData['labels'],
            'datasets' => $projectRawData['datasets'],
        ]);
        $projectChart->setOptions($this->getDefaultOptions('Heures par projet'));

        // Graphique par Activité
        // On passe un tableau [$this->getUser()] pour la compatibilité avec le service
        $activityRawData = $timeStatsService->getActivityChartRawData2([$this->getUser()], $startDate, $endDate);
        $activityChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $activityChart->setData([
            'labels' => $activityRawData['labels'],
            'datasets' => $activityRawData['datasets'],
        ]);
        $activityChart->setOptions($this->getDefaultOptions('Heures par activité'));

        // --- 4. AUTRES STATS ---
        $completionStats = $timeStatsService->getCompletionStats($this->getUser());

        // --- 5. RENDU ---
        return $this->render('home/home.html.twig', [
            'projectChart' => $projectChart,
            'activityChart' => $activityChart,
            'monthName' => $startDate->format('F Y'),
            'completionStats' => $completionStats,
            'current_period' => $period,
            'current_start_date' => $startDate->format('Y-m-d'),
            'current_end_date' => $endDate->format('Y-m-d'),
        ]);
    }

    /**
     * Configuration commune pour les graphiques
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