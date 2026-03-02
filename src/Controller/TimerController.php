<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TimerController extends AbstractController
{
    // src/Controller/TimerController.php
    #[Route('/timer', name: 'app_timer')]
    public function index(): Response
    {
        return $this->render('test.html.twig'); // Change this to your filename
    }
}
