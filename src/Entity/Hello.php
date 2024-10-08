<?php

namespace App\Entity;

use App\Repository\HelloRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HelloRepository::class)]
class Hello
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lucky_number = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLuckyNumber(): ?string
    {
        return $this->lucky_number;
    }

    public function setLuckyNumber(?string $lucky_number): static
    {
        $this->lucky_number = $lucky_number;

        return $this;
    }
}
