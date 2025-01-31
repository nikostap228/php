<?php

namespace App\Entity;

use App\Repository\PortfolioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
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

    #[ORM\OneToMany(targetEntity: Depositary::class, mappedBy: 'portfolio', cascade: ['persist', 'remove'])]
    private Collection $depositaries;

    #[ORM\OneToMany(targetEntity: PortfolioStock::class, mappedBy: 'portfolio', cascade: ['persist', 'remove'])]
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
     * Добавить средства в баланс
     */
    public function addCash(float $amount): self
    {
        $this->balance += $amount;
        return $this;
    }

    /**
     * Уменьшить средства из баланса
     */
    public function deductCash(float $amount): self
    {
        $this->balance -= $amount;
        $this->frozenCash += $amount;
        return $this;
    }

    /**
     * Ревертировать списание средств
     */
    public function revertCashDeduction(float $amount): self
    {
        $this->balance += $amount;
        $this->frozenCash -= $amount;
        return $this;
    }

    /**
     * Добавить акции в портфель
     */
    public function addStock(EntityManagerInterface $entityManager, Stock $stock, int $quantity): void
    {
        $depositary = $entityManager->getRepository(Depositary::class)->findOneBy([
            'portfolio' => $this,
            'stock' => $stock,
        ]);
        if (!$depositary) {
            $depositary = new Depositary();
            $depositary->setPortfolio($this);
            $depositary->setStock($stock);
            $depositary->setQuantity(0);
        }
        $depositary->setQuantity($depositary->getQuantity() + $quantity);
        $entityManager->persist($depositary);

        // Обновляем PortfolioStock
        $portfolioStock = $entityManager->getRepository(PortfolioStock::class)->findOneBy([
            'portfolio' => $this,
            'stock' => $stock,
        ]);
        if (!$portfolioStock) {
            $portfolioStock = new PortfolioStock();
            $portfolioStock->setPortfolio($this);
            $portfolioStock->setStock($stock);
            $portfolioStock->setQuantity(0);
        }
        $portfolioStock->setQuantity($portfolioStock->getQuantity() + $quantity);
        $entityManager->persist($portfolioStock);
    }

    /**
     * Удалить акции из портфеля
     */
    public function removeStock(EntityManagerInterface $entityManager, Stock $stock, int $quantity): void
    {
        $depositary = $entityManager->getRepository(Depositary::class)->findOneBy([
            'portfolio' => $this,
            'stock' => $stock,
        ]);
        if ($depositary) {
            $depositary->setQuantity($depositary->getQuantity() - $quantity);
            $entityManager->persist($depositary);
        }

        // Обновляем PortfolioStock
        $portfolioStock = $entityManager->getRepository(PortfolioStock::class)->findOneBy([
            'portfolio' => $this,
            'stock' => $stock,
        ]);
        if ($portfolioStock) {
            $portfolioStock->setQuantity($portfolioStock->getQuantity() - $quantity);
            $entityManager->persist($portfolioStock);
        }
    }
}