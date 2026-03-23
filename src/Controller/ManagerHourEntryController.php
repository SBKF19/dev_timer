<?php

namespace App\Controller;

use App\Repository\HourEntryRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\HourEntry;
use App\Form\ManagerHourEntryType;
use App\Repository\ScheduleRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/manager-hour-entry', name: 'manager_hour_entry_')]
class ManagerHourEntryController extends AbstractController
{
    #[Route('/', name: 'list')]
    public function list(
        Request $request, 
        HourEntryRepository $hourEntryRepository,
        UserRepository $userRepository,
        ProjectRepository $projectRepository,
        PaginatorInterface $paginator
    ): Response {
        $today = new \DateTime();
        $period = $request->query->get('period');

        // --- 1. GESTION DES DATES ---
        if ($period === 'week') {
            $startDate = (clone $today)->modify('Monday this week');
            $endDate = (clone $today)->modify('Sunday this week');
        } elseif ($period === 'month') {
            $startDate = (clone $today)->modify('first day of this month');
            $endDate = (clone $today)->modify('last day of this month');
        } elseif ($period === 'year') {
            $startDate = (clone $today)->modify('first day of January this year');
            $endDate = (clone $today)->modify('last day of December this year');
        } else {
            $startDateStr = $request->query->get('start_date');
            $startDate = $startDateStr ? new \DateTime($startDateStr) : (clone $today)->modify('Monday this week');
            
            $endDateStr = $request->query->get('end_date');
            $endDate = $endDateStr ? new \DateTime($endDateStr) : (clone $today)->modify('Sunday this week');
        }

        // --- 2. GESTION DES FILTRES (MULTIPLE) ---
        $params = $request->query->all();
        $userId = $params['user_id'] ?? null;
        $projectId = $params['project_id'] ?? null;

        if ($userId === "") $userId = null;
        if ($projectId === "") $projectId = null;

        // --- 3. RÉCUPÉRATION DES DONNÉES ---
        $query = $hourEntryRepository->getManagerFilteredQuery($startDate, $endDate, $userId, $projectId);

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('manager_hour_entry/index.html.twig', [
            'pagination' => $pagination,
            'users' => $userRepository->findBy(['status' => true], ['lastname' => 'ASC']),
            'projects' => $projectRepository->findAll(),
            'current_period' => $period,
            'current_start_date' => $startDate->format('Y-m-d'),
            'current_end_date' => $endDate->format('Y-m-d'),
            'current_user_id' => $userId,
            'current_project_id' => $projectId,
        ]);
    }

    #[Route('/add', name: 'add')]
    public function add(Request $request, HourEntryRepository $hourRepo, ScheduleRepository $scheduleRepo): Response 
    {
        $hourEntry = new HourEntry();
        $hourEntry->setStartDate((new \DateTime())->setTime(9, 0));
        $hourEntry->setEndDate((new \DateTime())->setTime(10, 0));
        $hourEntry->setCreatedAt(new \DateTime());
        $hourEntry->setCreatedBy($this->getUser());

        $form = $this->createForm(ManagerHourEntryType::class, $hourEntry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \DateTime $chosenDate */
            $chosenDate = $form->get('entryDate')->getData();
            $dayOfWeek = (int)$chosenDate->format('N');

            // 1. Appliquer la date aux objets Time
            $this->applyDateToTime($hourEntry->getStartDate(), $chosenDate);
            $this->applyDateToTime($hourEntry->getEndDate(), $chosenDate);

            // --- 2. LOGIQUE DE VALIDATION (RECUPEREE DU 1ER CONTROLLER) ---
            
            // A. Vérification du planning actif
            $activeSchedules = $scheduleRepo->findActiveSchedulesByDay($dayOfWeek, $chosenDate);
            $isValidRange = false;
            $entryStart = $hourEntry->getStartDate()->format('H:i');
            $entryEnd = $hourEntry->getEndDate()->format('H:i');

            foreach ($activeSchedules as $s) {
                if ($entryStart >= $s->getStartTime()->format('H:i') && $entryEnd <= $s->getEndTime()->format('H:i')) {
                    $isValidRange = true;
                    break;
                }
            }

            if (!$isValidRange) {
                $this->addFlash('error', "L'horaire saisi ne correspond à aucun planning actif pour ce jour.");
            } 
            // B. Vérification du chevauchement (pour le développeur sélectionné !)
            elseif ($hourRepo->hasOverlappingEntry($hourEntry->getUser(), $hourEntry->getStartDate(), $hourEntry->getEndDate())) {
                $this->addFlash('error', "Attention : le développeur sélectionné a déjà une saisie sur ce créneau.");
            } 
            else {
                // 3. SAUVEGARDE
                $hourRepo->save($hourEntry, true);
                $this->addFlash('success', 'La saisie a été ajoutée avec succès.');
                return $this->redirectToRoute('manager_hour_entry_list');
            }
        }

        // Pour l'affichage initial du bandeau bleu
        $dayOfWeekInitial = (int)(new \DateTime())->format('N');
        $currentSchedules = $scheduleRepo->findBy(['dayOfWeek' => $dayOfWeekInitial]);

        return $this->render('manager_hour_entry/form.html.twig', [
            'form' => $form->createView(),
            'hourEntry' => $hourEntry,
            'editMode' => false,
            'currentSchedules' => $currentSchedules,
        ]);
    }

    #[Route('/edit/{id}', name: 'edit')]
    public function edit(Request $request, HourEntry $hourEntry, HourEntryRepository $hourRepo, ScheduleRepository $scheduleRepo): Response 
    {
        // On mémorise la date actuelle pour l'affichage initial
        $currentDate = $hourEntry->getStartDate();
        $dayOfWeekInitial = (int)$currentDate->format('N');
        $currentSchedules = $scheduleRepo->findBy(['dayOfWeek' => $dayOfWeekInitial]);

        $form = $this->createForm(ManagerHourEntryType::class, $hourEntry, [
            'default_date' => $currentDate,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \DateTime $chosenDate */
            $chosenDate = $form->get('entryDate')->getData();
            $dayOfWeek = (int)$chosenDate->format('N');

            // 1. Synchronisation Date + Heure
            $this->applyDateToTime($hourEntry->getStartDate(), $chosenDate);
            $this->applyDateToTime($hourEntry->getEndDate(), $chosenDate);

            // 2. VALIDATIONS
            
            // A. Vérification du planning
            $activeSchedules = $scheduleRepo->findActiveSchedulesByDay($dayOfWeek, $chosenDate);
            $isValidRange = false;
            $entryStart = $hourEntry->getStartDate()->format('H:i');
            $entryEnd = $hourEntry->getEndDate()->format('H:i');

            foreach ($activeSchedules as $s) {
                if ($entryStart >= $s->getStartTime()->format('H:i') && $entryEnd <= $s->getEndTime()->format('H:i')) {
                    $isValidRange = true;
                    break;
                }
            }

            if (!$isValidRange) {
                $this->addFlash('error', "Modification impossible : l'horaire est en dehors du planning prévu.");
            } 
            // B. Vérification du chevauchement (On passe l'ID de l'entité actuelle en 4ème paramètre)
            elseif ($hourRepo->hasOverlappingEntry($hourEntry->getUser(), $hourEntry->getStartDate(), $hourEntry->getEndDate(), $hourEntry->getId())) {
                $this->addFlash('error', "Attention : ce créneau chevauche une autre saisie de ce développeur.");
            } 
            else {
                // 3. SAUVEGARDE
                $hourRepo->save($hourEntry, true);
                $this->addFlash('success', 'La saisie a été modifiée avec succès.');
                return $this->redirectToRoute('manager_hour_entry_list');
            }
        }

        return $this->render('manager_hour_entry/form.html.twig', [
            'form' => $form->createView(),
            'hourEntry' => $hourEntry,
            'editMode' => true,
            'currentSchedules' => $currentSchedules,
        ]);
    }

    /**
     * Utilitaire pour copier la partie Date d'un objet vers un autre 
     * sans modifier la partie Heure.
     */
    private function applyDateToTime(\DateTime $timeTarget, \DateTime $dateSource): void
    {
        $timeTarget->setDate(
            (int)$dateSource->format('Y'),
            (int)$dateSource->format('m'),
            (int)$dateSource->format('d')
        );
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, HourEntry $hourEntry, HourEntryRepository $repository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$hourEntry->getId(), $request->request->get('_token'))) {
            $repository->remove($hourEntry, true);
            $this->addFlash('success', 'La saisie a été supprimée.');
        }

        return $this->redirectToRoute('manager_hour_entry_list', $request->query->all());
    }
    #[Route('/get-schedule/{date}', name: 'get_schedule_api', methods: ['GET'])]
    public function getScheduleApi(string $date, ScheduleRepository $scheduleRepo): JsonResponse
    {
        $dateTime = new \DateTime($date);
        $dayOfWeek = (int)$dateTime->format('N');
        
        $schedules = $scheduleRepo->findBy(['dayOfWeek' => $dayOfWeek]);
        
        $data = array_map(fn($s) => [
            'start' => $s->getStartTime()->format('H:i'),
            'end' => $s->getEndTime()->format('H:i')
        ], $schedules);

        return new JsonResponse($data);
    }

    #[Route('/get-occupancy/{userId}/{date}', name: 'get_occupancy_api', methods: ['GET'])]
    public function getOccupancyApi(int $userId, string $date, HourEntryRepository $hourRepo, UserRepository $userRepo): JsonResponse
    {
        $user = $userRepo->find($userId);
        $dateTime = new \DateTime($date);
        
        // On récupère les saisies existantes pour ce dev à cette date
        $entries = $hourRepo->findByUserAndDate($user, $dateTime);
        
        $data = array_map(fn($e) => [
            'start' => $e->getStartDate()->format('H:i'),
            'end' => $e->getEndDate()->format('H:i'),
            'activity' => $e->getActivity() ? $e->getActivity()->getLabel() : 'N/A'
        ], $entries);

        return new JsonResponse($data);
    }
}