<?php

namespace App\Controller;

use App\Repository\HourEntryRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\TimeStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/reporting-global', name: 'reporting_global_')]
class ReportingGlobalController extends AbstractController
{
    #[Route('/', name: 'list', methods: ['GET'])]
    public function list(
        Request $request,
        HourEntryRepository $hourEntryRepo,
        UserRepository $userRepo,
        ProjectRepository $projectRepo,
        TimeStatsService $timeStatsService,
        ChartBuilderInterface $chartBuilder
    ): Response {
        $today = new \DateTime();
        $period = $request->query->get('period');

        // Cast en array pour gérer les sélections multiples
        $userIds = (array) $request->query->all('user_id') ?: null;
        $projectIds = (array) $request->query->all('project_id') ?: null;

        // --- 1. GESTION DES DATES (Compatible \DateTime / Doctrine) ---
        if ($period === 'week') {
            $startDate = (clone $today)->modify('Monday this week')->setTime(0, 0);
            $endDate = (clone $today)->modify('Sunday this week')->setTime(23, 59, 59);
        } elseif ($period === 'month') {
            $startDate = (clone $today)->modify('first day of this month')->setTime(0, 0);
            $endDate = (clone $today)->modify('last day of this month')->setTime(23, 59, 59);
        } elseif ($period === 'year') {
            $startDate = (clone $today)->modify('first day of January this year')->setTime(0, 0);
            $endDate = (clone $today)->modify('last day of December this year')->setTime(23, 59, 59);
        } else {
            $startDateStr = $request->query->get('start_date');
            $startDate = $startDateStr ? new \DateTime($startDateStr) : (clone $today)->modify('Monday this week')->setTime(0, 0);

            $endDateStr = $request->query->get('end_date');
            $endDate = $endDateStr ? new \DateTime($endDateStr) : (clone $today)->modify('Sunday this week')->setTime(23, 59, 59);
        }

        // --- 2. RÉCUPÉRATION DES UTILISATEURS POUR LE SERVICE ---
        $usersForStats = $userIds ? $userRepo->findBy(['id' => $userIds]) : $userRepo->findBy(['status' => true]);

        // Appel au service (Heures saisies vs Manquantes)
        $statsGeneral = $timeStatsService->getManagerStats($usersForStats, $startDate, $endDate, $projectIds);

        // --- 3. CALCUL DEV VS NON-DEV ---
        $entries = $hourEntryRepo->getQueryForManagerList($startDate, $endDate, $userIds, $projectIds)->getResult();

        $totalDevMinutes = 0;
        $totalNonDevMinutes = 0;

        foreach ($entries as $entry) {
            $diff = $entry->getStartDate()->diff($entry->getEndDate());
            $minutes = ($diff->h * 60) + $diff->i;

            // Vérification via l'entité Activities
            if ($entry->getActivity()?->isDeveloping()) {
                $totalDevMinutes += $minutes;
            } else {
                $totalNonDevMinutes += $minutes;
            }
        }

        //graphique par project

        $rawData = $timeStatsService->getProjectChartRawData($usersForStats, $startDate, $endDate, $projectIds);

        $chart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => $rawData['labels'],
            'datasets' => $rawData['datasets'],
        ]);

        $chart->setOptions([
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => ['stacked' => true],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'Heures']
                ],
            ],
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ]);

        //graphique par activité

        $activityRawData = $timeStatsService->getActivityChartRawData($usersForStats, $startDate, $endDate, $projectIds);
        $activityChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $activityChart->setData([
            'labels' => $activityRawData['labels'],
            'datasets' => $activityRawData['datasets'],
        ]);

        $activityChart->setOptions([
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => ['stacked' => true],
                'y' => [
                    'stacked' => true, 
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'Heures par activité']
                ],
            ],
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ]);

        // Graphique Camembert par Utilisateur
        $userRawData = $timeStatsService->getUserTotalDevChartRawData($usersForStats, $startDate, $endDate, $projectIds);
        $userChart = $chartBuilder->createChart(Chart::TYPE_PIE);
        $userChart->setData($userRawData);

        $userChart->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ]);

        $activityTotalData = $timeStatsService->getActivityTotalChartRawData($usersForStats, $startDate, $endDate, $projectIds);
        $activityPieChart = $chartBuilder->createChart(Chart::TYPE_PIE);
        $activityPieChart->setData($activityTotalData);
        $activityPieChart->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ]);

        return $this->render('reporting_global/index.html.twig', [
            'users' => $userRepo->findBy(['status' => true], ['lastname' => 'ASC']),
            'projects' => $projectRepo->findAll(),
            'current_period' => $period,
            'current_start_date' => $startDate->format('Y-m-d'),
            'current_end_date' => $endDate->format('Y-m-d'),
            'current_user_id' => $userIds,
            'current_project_id' => $projectIds,
            'statSaisies' => $statsGeneral['saisie'],
            'statManquantes' => $statsGeneral['restant'],
            'statDev' => sprintf('%dh%02d', floor($totalDevMinutes / 60), $totalDevMinutes % 60),
            'statNonDev' => sprintf('%dh%02d', floor($totalNonDevMinutes / 60), $totalNonDevMinutes % 60),
            'projectChart' => $chart,
            'activityChart' => $activityChart,
            'userChart' => $userChart,
            'activityPieChart' => $activityPieChart,
        ]);
    }
}