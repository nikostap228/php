<?php

namespace App\Entity;

use App\Repository\PortfolioStockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortfolioStockRepository::class)]
class PortfolioStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Portfolio::class, inversedBy: 'portfolioStocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Portfolio $portfolio = null;

    #[ORM\ManyToOne(targetEntity: Stock::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Stock $stock = null;

    #[ORM\Column]
    private int $quantity = 0;

    #[ORM\Column]
    private int $frozen = 0;

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

    public function getQuantity(): int
    {
        return $this->quantity;
    }
    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getFrozen(): int
    {
        return $this->frozen;
    }
    public function setFrozen(int $frozen): self
    {
        $this->frozen = $frozen;
        return $this;
    }

    /**
     * Get the available quantity of stocks (total quantity minus frozen)
     */
    public function getAvailableQuantity(): int
    {
        return $this->quantity - $this->frozen;
    }
}