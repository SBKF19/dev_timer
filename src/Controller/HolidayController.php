<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Holiday;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\HolidayRepository;
use App\Form\HolidayForm;

final class HolidayController extends AbstractController
{
    #[Route('/holiday', name: 'app_holiday')]
    public function index(HolidayRepository $holidayRepository): Response
    {
        $allHolidays = $holidayRepository->findBy([], ['date' => 'ASC']);

        $groupedHolidays = [];
        foreach ($allHolidays as $holiday) {
            $year = $holiday->getDate()->format('Y');
            $groupedHolidays[$year][] = $holiday;
    }

    return $this->render('holiday/holiday_list.html.twig', [
        'groupedHolidays' => $groupedHolidays,
    ]);
    }

    #[Route('/holiday/add', name: 'app_holiday_add')]
    public function addHoliday(Request $request, EntityManagerInterface $entityManager): Response
    {
        $holiday = new Holiday();
        $form = $this->createForm(HolidayForm::class, $holiday);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $holidayRepository = $entityManager->getRepository(Holiday::class);
            $existingHoliday = $holidayRepository->findOneBy([
                'date' => $holiday->getDate(),
                'name' => $holiday->getName()
            ]);

            if ($existingHoliday) {
                $this->addFlash('error', 'Un jour férié existe déjà pour cette date.');
            } else {
                $entityManager->persist($holiday);
                $entityManager->flush();
                $this->addFlash('success', 'Jour férié ajouté avec succès !');
                return $this->redirectToRoute('app_holiday');
            }
        }

        return $this->render('holiday/holiday_add.html.twig', [
            'holidayForm' => $form->createView(),
        ]);
    }

    #[Route('/holiday/edit/{id}', name: 'app_holiday_edit')]
    public function editHoliday(Request $request, EntityManagerInterface $entityManager, Holiday $holiday): Response
    {
        $form = $this->createForm(HolidayForm::class, $holiday);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $date = $holiday->getDate();
            $name = $holiday->getName();

            $existingHoliday = $entityManager->getRepository(Holiday::class)->findOneBy([
                'date' => $date,
                'name' => $name
            ]);
            if ($existingHoliday && $existingHoliday->getId() !== $holiday->getId()) {
                $this->addFlash('error', 'Un jour férié existe déjà pour cette date.');
            } else {
                $entityManager->flush();
                $this->addFlash('success', 'Jour férié mis à jour avec succès !');
                return $this->redirectToRoute('app_holiday');
            }
        }

        return $this->render('holiday/holiday_edit.html.twig', [
            'holidayForm' => $form->createView(),
            'holiday'     => $holiday,
        ]);
    }

    #[Route('/holiday/delete/{id}', name: 'app_holiday_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, Holiday $holiday): Response
    {
        if ($this->isCsrfTokenValid('delete_holiday', $request->request->get('_token'))) {
            $entityManager->remove($holiday);
            $entityManager->flush();
            $this->addFlash('success', 'Jour férié supprimé !');
        }

        return $this->redirectToRoute('app_holiday');
    }
}
