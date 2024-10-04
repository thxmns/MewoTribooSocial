<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use App\Entity\Actualites;
use App\Repository\ActualitesRepository;
use App\Repository\SectionsRepository;
use App\Repository\PagesRepository;


class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response {
        return $this->render('default/index.html.twig', [
            'controller_name' => 'DefaultController',
        ]);
    } 

    
} 

