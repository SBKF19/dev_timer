<?php

namespace App\Controller;

use App\Entity\HourEntry;
use App\Form\HourEntryType;
use App\Repository\HourEntryRepository;
use App\Repository\ScheduleRepository;
use App\Repository\HolidayRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\TimeStatsService;

#[Route('/hour-entry', name: 'hour_entry_')]
final class HourEntryController extends AbstractController
{
    #[Route('/', name: 'list')]
    public function list(ScheduleRepository $scheduleRepository): Response
    {
    $schedules = $scheduleRepository->findBy(['deleted_at' => null]);
    $businessHours = [];
    
    // Initialisation
    $minTime = '08:00'; 
    $maxTime = '18:00';

    if (!empty($schedules)) {
        $startTimes = [];
        $endTimes = [];

        foreach ($schedules as $s) {
            $start = $s->getStartTime()->format('H:i');
            $end = $s->getEndTime()->format('H:i');
            
            $startTimes[] = $start;
            $endTimes[] = $end;

            $businessHours[] = [
                'daysOfWeek' => [$s->getDayOfWeek()],
                'startTime' => $start,
                'endTime' => $end,
            ];
        }

        // On calcule le min et le max
        // On enlève 1h au min et on ajoute 1h au max pour laisser un peu de marge visuelle
        $minHour = (int)substr(min($startTimes), 0, 2);
        $maxHour = (int)substr(max($endTimes), 0, 2);

        $minTime = str_pad(max(0, $minHour - 1), 2, '0', STR_PAD_LEFT) . ':00:00';
        $maxTime = str_pad(min(24, $maxHour + 1), 2, '0', STR_PAD_LEFT) . ':00:00';
    }

    return $this->render('hour_entry/calendar.html.twig', [
        'businessHours' => $businessHours,
        'minTime' => $minTime,
        'maxTime' => $maxTime
    ]);
    }

    /**
     * API pour FullCalendar
     */
    #[Route('/events', name: 'events', methods: ['GET'])]
    public function events(HourEntryRepository $hourEntryRepository, HolidayRepository $holidayRepository): JsonResponse
    {
        $entries = $hourEntryRepository->findBy(['user' => $this->getUser()]);
        $holidays = $holidayRepository->findAll();
        $events = [];

        foreach ($entries as $entry) {
            $activity = $entry->getActivity();
            $project = $entry->getProject();

            // 1. Uniquement la couleur de l'Activité
            $color = $activity ? $activity->getColor() : '#3788d8';

            // 2. Titre : "Activité - Projet"
            $title = $activity ? $activity->getLabel() : 'Sans activité';
            if ($project) {
                $title .= ' - ' . $project->getName();
            }

            $events[] = [
                'id' => 'entry_' . $entry->getId(),
                'title' => $title,
                'start' => $entry->getStartDate()?->format('Y-m-d\TH:i:s'),
                'end' => $entry->getEndDate()?->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#ffffff',
            ];
        }

        foreach ($holidays as $holiday) {
        $grayColor = '#4b5563';

        $events[] = [
            'id' => 'holiday_' . $holiday->getId(),
            'title' => '🎉 ' . $holiday->getName(),
            'start' => $holiday->getDate()?->format('Y-m-d'), 
            'allDay' => true,
            'backgroundColor' => $grayColor,
            'borderColor' => $grayColor,
            'display' => 'background', // Optionnel : met l'événement en arrière-plan
            'editable' => false,       // Empêche de le déplacer par erreur
        ];
    }

        return new JsonResponse($events);
    }

    #[Route('/add', name: 'add')]
    public function add(
        Request $request, 
        HourEntryRepository $hourEntryRepository, 
        ScheduleRepository $scheduleRepository
    ): Response {
        $startParam = $request->query->get('start');
        
        if ($startParam && str_contains($startParam, ' ')) {
            $startParam = explode(' ', $startParam)[0];
        }

        try {
            $dateReference = new \DateTime($startParam ?? 'now');
        } catch (\Exception $e) {
            $dateReference = new \DateTime('now');
        }

        $dayOfWeek = (int)$dateReference->format('N');

        $hourEntry = new HourEntry();
        $hourEntry->setStartDate(clone $dateReference);
        $hourEntry->setEndDate((clone $dateReference)->modify('+1 hour'));
        $hourEntry->setUser($this->getUser());
        $hourEntry->setCreatedBy($this->getUser());
        $hourEntry->setCreatedAt(new \DateTime());

        $form = $this->createForm(HourEntryType::class, $hourEntry, [
            'day_of_week' => $dayOfWeek 
        ]);

        $form->handleRequest($request);

        // Fixation de la date (sécurité habituelle)
            $hourEntry->getStartDate()->setDate(
                (int)$dateReference->format('Y'), (int)$dateReference->format('m'), (int)$dateReference->format('d')
            );
            $hourEntry->getEndDate()->setDate(
                (int)$dateReference->format('Y'), (int)$dateReference->format('m'), (int)$dateReference->format('d')
            );

        if ($form->isSubmitted() && $form->isValid()) {

            // --- 1. RÉCUPÉRATION DU PLANNING ACTIF À CETTE DATE ---
            $activeSchedules = $scheduleRepository->findActiveSchedulesByDay($dayOfWeek, $dateReference);

            // --- 2. VÉRIFICATION DU CRÉNEAU ---
            $isValidRange = false;
            $entryStart = $hourEntry->getStartDate()->format('H:i');
            $entryEnd = $hourEntry->getEndDate()->format('H:i');

            foreach ($activeSchedules as $s) {
                $sStart = $s->getStartTime()->format('H:i');
                $sEnd = $s->getEndTime()->format('H:i');

                // Si la saisie est incluse dans une plage du planning
                if ($entryStart >= $sStart && $entryEnd <= $sEnd) {
                    $isValidRange = true;
                    break;
                }
            }

            if (!$isValidRange) {
                $this->addFlash('error', "L'horaire saisi ne correspond à aucun planning actif pour cette date.");
            } 
            elseif ($hourEntryRepository->hasOverlappingEntry($this->getUser(), $hourEntry->getStartDate(), $hourEntry->getEndDate())) {
                $this->addFlash('error', 'Chevauchement détecté.');
            } 
            else {
                $hourEntryRepository->save($hourEntry, true);
                $this->addFlash('success', 'Saisie ajoutée !');
                return $this->redirectToRoute('hour_entry_list');
            }
        }

        return $this->render('hour_entry/form.html.twig', [
            'form' => $form->createView(),
            'editMode' => false,
            'hourEntry' => $hourEntry,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/edit/{id}', name: 'edit')]
    public function edit(
        HourEntry $hourEntry, 
        Request $request, 
        HourEntryRepository $hourEntryRepository,
        ScheduleRepository $scheduleRepository // N'oublie pas l'injection ici
    ): Response {
        $dateBase = clone $hourEntry->getStartDate();
        $dayOfWeek = (int)$dateBase->format('N');

        $form = $this->createForm(HourEntryType::class, $hourEntry, [
            'day_of_week' => $dayOfWeek
        ]);
        
        $form->handleRequest($request);

        // 1. Restauration de la date sur les objets DateTime (indispensable avec TimeType)
            $hourEntry->getStartDate()->setDate(
                (int)$dateBase->format('Y'), (int)$dateBase->format('m'), (int)$dateBase->format('d')
            );
            $hourEntry->getEndDate()->setDate(
                (int)$dateBase->format('Y'), (int)$dateBase->format('m'), (int)$dateBase->format('d')
            );

        if ($form->isSubmitted() && $form->isValid()) {

            // 2. Récupération du planning de l'époque
            $activeSchedules = $scheduleRepository->findActiveSchedulesByDay($dayOfWeek, $dateBase);

            // 3. Vérification du créneau
            $isValidRange = false;
            $entryStart = $hourEntry->getStartDate()->format('H:i');
            $entryEnd = $hourEntry->getEndDate()->format('H:i');

            foreach ($activeSchedules as $s) {
                $sStart = $s->getStartTime()->format('H:i');
                $sEnd = $s->getEndTime()->format('H:i');

                if ($entryStart >= $sStart && $entryEnd <= $sEnd) {
                    $isValidRange = true;
                    break;
                }
            }

            // 4. cascade de validations
            if (!$isValidRange) {
                $this->addFlash('error', "Modification impossible : l'horaire est en dehors du planning de l'époque.");
            } 
            elseif ($hourEntryRepository->hasOverlappingEntry($this->getUser(), $hourEntry->getStartDate(), $hourEntry->getEndDate(), $hourEntry->getId())) {
                $this->addFlash('error', 'Attention : Cette modification crée un chevauchement.');
            } 
            else {
                $hourEntryRepository->save($hourEntry, true);
                $this->addFlash('success', 'Saisie horaire modifiée avec succès !');
                return $this->redirectToRoute('hour_entry_list');
            }
        }

        return $this->render('hour_entry/form.html.twig', [
            'form' => $form->createView(),
            'editMode' => true,
            'hourEntry' => $hourEntry,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(HourEntry $hourEntry, Request $request, HourEntryRepository $hourEntryRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $hourEntry->getId(), $request->request->get('_token'))) {
        
            $hourEntryRepository->remove($hourEntry, true);
            
            $this->addFlash('success', 'La saisie horaire a été supprimée.');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('hour_entry_list');
    }


   #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getStats(Request $request, TimeStatsService $statsService): JsonResponse 
    {
        $startParam = explode(' ', $request->query->get('start', 'now'))[0];
        $endParam = explode(' ', $request->query->get('end', 'now'))[0];

        $startDate = new \DateTime($startParam);
        $endDate = new \DateTime($endParam);

        $stats = $statsService->getWeeklyStats($this->getUser(), $startDate, $endDate);

        return new JsonResponse($stats);
    }
}