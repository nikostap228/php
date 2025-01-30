<?php

namespace App\Entity;

use App\Repository\ApplicationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ApplicationRepository::class)]
class Application
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Portfolio::class, inversedBy: 'applications')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Portfolio must be selected.')]
    private ?Portfolio $portfolio = null;

    #[ORM\ManyToOne(targetEntity: Stock::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Stock must be selected.')]
    private ?Stock $stock = null;

    #[ORM\Column]
    #[Assert\Positive(message: 'Quantity must be a positive number.')]
    private ?int $quantity = null;

    #[ORM\Column]
    #[Assert\Positive(message: 'Cost must be a positive number.')]
    private ?float $cost = null;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: ['buy', 'sell'], message: 'Action must be either "buy" or "sell".')]
    private ?string $action = null;

    // Геттеры и сеттеры
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPortfolio(): ?Portfolio
    {
        return $this->portfolio;
    }

    public function setPortfolio(?Portfolio $portfolio): self
    {
        $this->portfolio = $portfolio;
        return $this;
    }

    public function getStock(): ?Stock
    {
        return $this->stock;
    }

    public function setStock(?Stock $stock): self
    {
        $this->stock = $stock;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getCost(): ?float
    {
        return $this->cost;
    }

    public function setCost(float $cost): self
    {
        $this->cost = $cost;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }
}
