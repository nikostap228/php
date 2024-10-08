<?php

namespace App\Controller;

use App\Service\GCDCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GCDController extends AbstractController
{
    private GCDCalculator $gcdCalculator;

    public function __construct(GCDCalculator $gcdCalculator)
    {
        $this->gcdCalculator = $gcdCalculator;
    }

    #[Route('/gcd/{number1}/{number2}', name: 'gcd_calculate', methods: ['GET'])]
    public function calculateGCD(int $number1, int $number2): Response
    {
        // Вычисляем НОД
        $gcd = $this->gcdCalculator->calculateGCD($number1, $number2);

        // Возвращаем результат в виде HTML-страницы
        return new Response("Наибольший общий делитель чисел $number1 и $number2: $gcd");
    }
}