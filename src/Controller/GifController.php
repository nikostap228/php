<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GifController extends AbstractController
{
    #[Route('/cat')]
    public function cat(): Response
    {
        return $this->render('./cat.html.twig');
    }
    #[Route('/dog')]
    public function dog(): Response
    {
        return $this->render('./dog.html.twig');
    }

    #[Route('/artur')]
    public function artur(): Response
    {
        return $this->render('./artur.html.twig');
    }
}
