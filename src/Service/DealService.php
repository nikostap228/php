<?php

namespace App\Service;

use App\Entity\Application;
use App\Entity\Depositary;
use App\Entity\Portfolio;
use App\Entity\PortfolioStock;
use App\Repository\ApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;

class DealService
{
    private EntityManagerInterface $entityManager;
    private ApplicationRepository $applicationRepository;

    public function __construct(EntityManagerInterface $entityManager, ApplicationRepository $applicationRepository)
    {
        $this->entityManager = $entityManager;
        $this->applicationRepository = $applicationRepository;
    }

    /**
     * Поиск подходящей заявки для данной заявки
     */
    public function findMatchingApplication(Application $application): ?Application
    {
        $stockId = $application->getStock()->getId();
        $quantity = $application->getQuantity();
        $price = $application->getCost();
        $action = $application->getAction();

        // Ищем заявки с противоположным действием
        $oppositeAction = $action === 'buy' ? 'sell' : 'buy';
        $matchingApplications = $this->applicationRepository->findBy([
            'stock' => $stockId,
            'quantity' => $quantity,
            'cost' => $price,
            'action' => $oppositeAction,
        ]);

        foreach ($matchingApplications as $matchingApplication) {
            // Проверяем, что заявки принадлежат разным пользователям
            if ($matchingApplication->getPortfolio()->getUser() !== $application->getPortfolio()->getUser()) {
                return $matchingApplication;
            }
        }

        return null;
    }

    /**
     * Выполнение обмена между двумя заявками с частичным исполнением
     */
    public function executeTrade(Application $application, Application $matchingApplication): void
    {
        $portfolioBuyer = $application->getAction() === 'buy' ? $application->getPortfolio() : $matchingApplication->getPortfolio();
        $portfolioSeller = $application->getAction() === 'sell' ? $application->getPortfolio() : $matchingApplication->getPortfolio();
        $stock = $application->getStock();
        $quantity = min($application->getQuantity(), $matchingApplication->getQuantity());
        $price = ($application->getCost() + $matchingApplication->getCost()) / 2;

        // Находим Depositary для покупателя и продавца
        $depositaryBuyer = $this->entityManager->getRepository(Depositary::class)->findOneBy([
            'portfolio' => $portfolioBuyer,
            'stock' => $stock,
        ]);
        $depositarySeller = $this->entityManager->getRepository(Depositary::class)->findOneBy([
            'portfolio' => $portfolioSeller,
            'stock' => $stock,
        ]);

        // Проверяем, достаточно ли замороженных акций у продавца
        if (!$depositarySeller || $depositarySeller->getFrozen() < $quantity) {
            throw new \Exception('Seller does not have enough frozen stocks to sell.');
        }

        // Проверяем, достаточно ли замороженных средств у покупателя
        $requiredFrozenCash = $quantity * $price;
        if ($portfolioBuyer->getFrozenCash() < $requiredFrozenCash) {
            throw new \Exception('Buyer does not have enough frozen cash for the purchase.');
        }

        // Перемещаем акции из замороженных активов продавца в портфель покупателя
        if (!$depositaryBuyer) {
            $depositaryBuyer = new Depositary();
            $depositaryBuyer->setPortfolio($portfolioBuyer);
            $depositaryBuyer->setStock($stock);
            $depositaryBuyer->setQuantity(0);
            $depositaryBuyer->setFrozen(0);
        }
        $depositaryBuyer->setQuantity($depositaryBuyer->getQuantity() + $quantity);

        // Уменьшаем замороженные акции у продавца
        $depositarySeller->setFrozen($depositarySeller->getFrozen() - $quantity);

        // Перемещаем замороженные средства покупателя в баланс продавца
        $portfolioBuyer->setFrozenCash($portfolioBuyer->getFrozenCash() - $requiredFrozenCash);
        $portfolioSeller->addCash($requiredFrozenCash);

        // Обновляем PortfolioStock для покупателя
        $portfolioStockBuyer = $this->entityManager->getRepository(PortfolioStock::class)->findOneBy([
            'portfolio' => $portfolioBuyer,
            'stock' => $stock,
        ]);
        if (!$portfolioStockBuyer) {
            $portfolioStockBuyer = new PortfolioStock();
            $portfolioStockBuyer->setPortfolio($portfolioBuyer);
            $portfolioStockBuyer->setStock($stock);
            $portfolioStockBuyer->setQuantity(0);
            $portfolioStockBuyer->setFrozen(0);
        }
        $portfolioStockBuyer->setQuantity($portfolioStockBuyer->getQuantity() + $quantity);

        // Обновляем PortfolioStock для продавца
        $portfolioStockSeller = $this->entityManager->getRepository(PortfolioStock::class)->findOneBy([
            'portfolio' => $portfolioSeller,
            'stock' => $stock,
        ]);
        if ($portfolioStockSeller) {
            $portfolioStockSeller->setQuantity($portfolioStockSeller->getQuantity() - $quantity);
        }

        // Сохраняем изменения
        $this->entityManager->persist($depositaryBuyer);
        $this->entityManager->persist($depositarySeller);
        $this->entityManager->persist($portfolioStockBuyer);
        if ($portfolioStockSeller) {
            $this->entityManager->persist($portfolioStockSeller);
        }
        $this->entityManager->persist($portfolioBuyer);
        $this->entityManager->persist($portfolioSeller);

        // Удаляем заявки
        $this->entityManager->remove($application);
        $this->entityManager->remove($matchingApplication);

        $this->entityManager->flush();
    }
}