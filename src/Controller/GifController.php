<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GifController extends AbstractController
{

    #[Route('/gif')]
    public function index(): Response
    {
        return $this->render('./index.html.twig');
    }
}
