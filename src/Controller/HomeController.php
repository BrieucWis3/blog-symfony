<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_main')]
    public function index(): Response
    {
        $message='Gloire à Yéhoshoua';
        return $this->render('home/home.html.twig', compact('message'));
    }
}

