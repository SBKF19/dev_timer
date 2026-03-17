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

#[Route('/hour-entry', name: 'hour_entry_')]
final class HourEntryController extends AbstractController
{
    #[Route('/', name: 'list')]
    public function list(ScheduleRepository $scheduleRepository): Response
    {
    $schedules = $scheduleRepository->findAll();
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
    public function add(Request $request, HourEntryRepository $hourEntryRepository): Response
    {
        $hourEntry = new HourEntry();
        
        // 1. On extrait la date de référence depuis l'URL (FullCalendar)
        $startParam = $request->query->get('start');
        $cleanStart = $startParam ? explode(' ', $startParam)[0] : 'now';
        $dateReference = new \DateTime($cleanStart);

        // Initialisation par défaut pour l'affichage du formulaire
        $hourEntry->setStartDate(clone $dateReference);
        $hourEntry->setEndDate(clone $dateReference);
        $hourEntry->setUser($this->getUser());
        $hourEntry->setCreatedBy($this->getUser());
        $hourEntry->setCreatedAt(new \DateTime());

        $form = $this->createForm(HourEntryType::class, $hourEntry);
        $form->handleRequest($request);

        // 2. CORRECTION CRUCIALE : On remet la bonne date AVANT la validation
        // car le TimeType l'a écrasée par 01/01/1970
        if ($form->isSubmitted()) {
            $hourEntry->getStartDate()->setDate(
                (int)$dateReference->format('Y'), 
                (int)$dateReference->format('m'), 
                (int)$dateReference->format('d')
            );
            $hourEntry->getEndDate()->setDate(
                (int)$dateReference->format('Y'), 
                (int)$dateReference->format('m'), 
                (int)$dateReference->format('d')
            );
        }

        // 3. Validation (le Callback du HourEntryType verra maintenant la VRAIE date)
        if ($form->isSubmitted() && $form->isValid()) {
            // Vérification du chevauchement (Overlap)
            if ($this->checkOverlap($hourEntry, $hourEntryRepository)) {
                $this->addFlash('error', 'Attention : Cette plage horaire chevauche une saisie existante.');
            } else {
                $hourEntryRepository->save($hourEntry, true);
                $this->addFlash('success', 'Saisie horaire ajoutée avec succès !');
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
    public function edit(HourEntry $hourEntry, Request $request, HourEntryRepository $hourEntryRepository): Response
    {
        // 1. On mémorise la date d'origine de la saisie
        $dateBase = clone $hourEntry->getStartDate();

        $form = $this->createForm(HourEntryType::class, $hourEntry);
        $form->handleRequest($request);

        // 2. On restaure la date d'origine AVANT la validation
        if ($form->isSubmitted()) {
            $hourEntry->getStartDate()->setDate(
                (int)$dateBase->format('Y'), 
                (int)$dateBase->format('m'), 
                (int)$dateBase->format('d')
            );
            $hourEntry->getEndDate()->setDate(
                (int)$dateBase->format('Y'), 
                (int)$dateBase->format('m'), 
                (int)$dateBase->format('d')
            );
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->checkOverlap($hourEntry, $hourEntryRepository)) {
                $this->addFlash('error', 'Attention : Cette modification crée un chevauchement.');
            } else {
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
            // Si vous avez un champ status/deletedAt comme dans UserController :
            // $hourEntry->setStatus(false); 
            // $hourEntryRepository->save($hourEntry, true);
            
            // Sinon, suppression réelle :
            $hourEntryRepository->remove($hourEntry, true);
            
            $this->addFlash('success', 'Saisie horaire supprimée avec succès.');
        }

        return $this->redirectToRoute('hour_entry_list');
    }

    private function checkOverlap(HourEntry $entry, HourEntryRepository $repository): bool
    {
        $userId = $this->getUser()->getId();
        $start = $entry->getStartDate();
        $end = $entry->getEndDate();
        $entryId = $entry->getId(); // NULL en mode add, un chiffre en mode edit

        // On cherche une saisie qui se chevauche
        $overlapping = $repository->createQueryBuilder('h')
            ->where('h.user = :user')
            ->andWhere('h.startDate < :end')
            ->andWhere('h.endDate > :start')
            ->setParameter('user', $userId)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        // En mode édition, on exclut la saisie actuelle de la recherche !
        if ($entryId) {
            $overlapping->andWhere('h.id != :id')
                        ->setParameter('id', $entryId);
        }

        return count($overlapping->getQuery()->getResult()) > 0;
    }
}