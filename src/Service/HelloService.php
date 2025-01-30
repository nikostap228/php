<?php

namespace App\Service;

use App\Repository\HelloRepository;

class HelloService
{
    public function __construct(private readonly HelloRepository $helloRepository)
    {

    }
    private const MIN_LUCKY_NUMBER = 1;
    private const MAX_LUCKY_NUMBER = 3;

    public function generateLuckyNumber(): string
    {
        $number = (string) rand(self::MIN_LUCKY_NUMBER, self::MAX_LUCKY_NUMBER);
        return $this->helloRepository->createLuckyNumber($number)->getLuckyNumber();
    }
}
