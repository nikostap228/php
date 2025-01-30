<?php

namespace App\Entity;

use App\Repository\PortfolioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortfolioRepository::class)]
class Portfolio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'portfolios')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private float $balance = 0;

    #[ORM\Column]
    private float $cash = 0;

    #[ORM\Column]
    private float $frozenCash = 0;

    #[ORM\OneToMany(targetEntity: Depositary::class, mappedBy: 'portfolio')]
    private Collection $depositaries;

    #[ORM\OneToMany(targetEntity: PortfolioStock::class, mappedBy: 'portfolio')]
    private Collection $portfolioStocks;

    public function __construct()
    {
        $this->depositaries = new ArrayCollection();
        $this->portfolioStocks = new ArrayCollection();
    }

    // Геттеры и сеттеры
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUser(): ?User
    {
        return $this->user;
    }
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }
    public function getBalance(): float
    {
        return $this->balance;
    }
    public function setBalance(float $balance): self
    {
        $this->balance = $balance;
        return $this;
    }
    public function getCash(): float
    {
        return $this->cash;
    }
    public function setCash(float $cash): self
    {
        $this->cash = $cash;
        return $this;
    }
    public function getFrozenCash(): float
    {
        return $this->frozenCash;
    }
    public function setFrozenCash(float $frozenCash): self
    {
        $this->frozenCash = $frozenCash;
        return $this;
    }

    /**
     * @return Collection<int, Depositary>
     */
    public function getDepositaries(): Collection
    {
        return $this->depositaries;
    }

    /**
     * @return Collection<int, PortfolioStock>
     */
    public function getPortfolioStocks(): Collection
    {
        return $this->portfolioStocks;
    }

    /**
     * Получить доступные средства (баланс минус замороженные средства)
     */
    public function getAvailableCash(): float
    {
        return $this->balance - $this->frozenCash;
    }

    /**
     * Получить доступные акции для конкретного тикера
     */
    public function getAvailableStocksForTicker(string $ticker): int
    {
        $total = 0;
        foreach ($this->portfolioStocks as $portfolioStock) {
            if ($portfolioStock->getStock()->getTicker() === $ticker) {
                $total += $portfolioStock->getAvailableQuantity();
            }
        }
        return $total;
    }

    /**
     * Deduct cash from the portfolio's balance
     */
    public function deductCash(float $amount): self
    {
        $this->balance -= $amount;
        $this->frozenCash += $amount;
        return $this;
    }

    /**
     * Revert cash deduction
     */
    public function revertCashDeduction(float $amount): self
    {
        $this->balance += $amount;
        $this->frozenCash -= $amount;
        return $this;
    }

    /**
     * Deduct stocks from the portfolio
     */
    public function deductStocks(string $ticker, int $quantity): self
    {
        foreach ($this->portfolioStocks as $portfolioStock) {
            if ($portfolioStock->getStock()->getTicker() === $ticker) {
                $portfolioStock->setQuantity($portfolioStock->getQuantity() - $quantity);
                $portfolioStock->setFrozen($portfolioStock->getFrozen() + $quantity);
                break;
            }
        }
        return $this;
    }

    /**
     * Revert stock deduction
     */
    public function revertStockDeduction(string $ticker, int $quantity): self
    {
        foreach ($this->portfolioStocks as $portfolioStock) {
            if ($portfolioStock->getStock()->getTicker() === $ticker) {
                $portfolioStock->setQuantity($portfolioStock->getQuantity() + $quantity);
                $portfolioStock->setFrozen($portfolioStock->getFrozen() - $quantity);
                break;
            }
        }
        return $this;
    }

    /**
     * Получить сток по тикеру
     */
    public function getStockByTicker(string $ticker): ?Stock
    {
        foreach ($this->portfolioStocks as $portfolioStock) {
            if ($portfolioStock->getStock()->getTicker() === $ticker) {
                return $portfolioStock->getStock();
            }
        }
        return null;
    }

    /**
     * Получить PortfolioStock по стоку
     */
    public function getPortfolioStock(Stock $stock): ?PortfolioStock
    {
        foreach ($this->portfolioStocks as $portfolioStock) {
            if ($portfolioStock->getStock() === $stock) {
                return $portfolioStock;
            }
        }
        return null;
    }
}