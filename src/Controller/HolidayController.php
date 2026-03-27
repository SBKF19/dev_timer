<?php

namespace App\Controller;

use App\Entity\Holiday;
use App\Form\HolidayFormType;
use App\Repository\HolidayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HolidayController extends AbstractController
{
    #[Route('/holiday', name: 'app_holiday')]
    public function index(HolidayRepository $holidayRepository, PaginatorInterface $paginator, Request $request): Response
    {
        $rawData = $request->query->all('year');
        
        $selectedYears = [];
        if (is_array($rawData)) {
            foreach ($rawData as $value) {
                $selectedYears[] = is_array($value) ? $value[0] : $value;
            }
        }
        $selectedYears = array_filter($selectedYears);

        $qb = $holidayRepository->createQueryBuilder('h');

        if (!empty($selectedYears)) {
            $orX = $qb->expr()->orX();
            foreach ($selectedYears as $key => $year) {
                $start = $year . '-01-01';
                $end = $year . '-12-31';
                $orX->add($qb->expr()->between('h.date', ":start$key", ":end$key"));
                $qb->setParameter("start$key", $start)
                   ->setParameter("end$key", $end);
            }
            $qb->andWhere($orX);
        }

        $query = $qb->orderBy('h.date', 'ASC')->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10 
        );

        $groupedHolidays = [];
        foreach ($pagination as $holiday) {
            $year = $holiday->getDate()->format('Y');
            $groupedHolidays[$year][] = $holiday;
        }

        $allHolidays = $holidayRepository->findAll();
        $yearsForFilter = [];
        foreach ($allHolidays as $h) {
            $yearsForFilter[] = $h->getDate()->format('Y');
        }
        $yearsForFilter = array_unique($yearsForFilter);
        rsort($yearsForFilter);

        return $this->render('holiday/holiday_list.html.twig', [
            'groupedHolidays' => $groupedHolidays,
            'pagination'      => $pagination,
            'years'           => $yearsForFilter,
            'current_years'   => $selectedYears ?: [],
        ]);
    }

    #[Route('/holiday/add', name: 'app_holiday_add')]
    public function addHoliday(Request $request, EntityManagerInterface $entityManager): Response
    {
        $holiday = new Holiday();
        $form = $this->createForm(HolidayFormType::class, $holiday);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentYear = (int)date('Y');
            $selectedYear = (int)$holiday->getDate()->format('Y');

            if ($selectedYear < $currentYear) {
                $this->addFlash('error', "L'année doit être égale ou supérieure à l'année en cours ($currentYear).");
            } else {
                $holidayRepository = $entityManager->getRepository(Holiday::class);
                $existingDate = $holidayRepository->findOneBy(['date' => $holiday->getDate()]);
                $existingName = $holidayRepository->findOneBy(['name' => $holiday->getName()]);

                if ($existingDate) {
                    $this->addFlash('error', 'Un jour férié existe déjà à cette date.');
                } elseif ($existingName) {
                    $this->addFlash('error', 'Ce nom de jour férié est déjà utilisé.');
                } else {
                    $entityManager->persist($holiday);
                    $entityManager->flush();
                    $this->addFlash('success', 'Jour férié ajouté avec succès !');
                    return $this->redirectToRoute('app_holiday');
                }
            }
        }

        return $this->render('holiday/holiday_add.html.twig', [
            'HolidayFormType' => $form->createView(),
        ]);
    }

    #[Route('/holiday/edit/{id}', name: 'app_holiday_edit')]
    public function editHoliday(Request $request, EntityManagerInterface $entityManager, Holiday $holiday): Response
    {
        $form = $this->createForm(HolidayFormType::class, $holiday);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentYear = (int)date('Y');
            $selectedYear = (int)$holiday->getDate()->format('Y');

            if ($selectedYear < $currentYear) {
                $this->addFlash('error', "L'année doit être égale ou supérieure à l'année en cours ($currentYear).");
            } else {
                $holidayRepository = $entityManager->getRepository(Holiday::class);
                $existingDate = $holidayRepository->findOneBy(['date' => $holiday->getDate()]);
                $existingName = $holidayRepository->findOneBy(['name' => $holiday->getName()]);

                $dateConflict = ($existingDate && $existingDate->getId() !== $holiday->getId());
                $nameConflict = ($existingName && $existingName->getId() !== $holiday->getId());

                if ($dateConflict) {
                    $this->addFlash('error', 'Un jour férié existe déjà à cette date.');
                } elseif ($nameConflict) {
                    $this->addFlash('error', 'Ce nom de jour férié est déjà utilisé.');
                } else {
                    $entityManager->flush();
                    $this->addFlash('success', 'Jour férié mis à jour avec succès !');
                    return $this->redirectToRoute('app_holiday');
                }
            }
        }

        return $this->render('holiday/holiday_edit.html.twig', [
            'HolidayFormType' => $form->createView(),
            'holiday' => $holiday,
        ]);
    }

    #[Route('/holiday/delete/{id}', name: 'app_holiday_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, Holiday $holiday): Response
    {
        if ($this->isCsrfTokenValid('delete' . $holiday->getId(), $request->request->get('_token'))) {
            $entityManager->remove($holiday);
            $entityManager->flush();
            $this->addFlash('success', 'Jour férié supprimé !');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_holiday');
    }
}