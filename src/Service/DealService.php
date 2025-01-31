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
        $portfolio1 = $application->getPortfolio();
        $portfolio2 = $matchingApplication->getPortfolio();
        $stock = $application->getStock();
        $quantity1 = $application->getQuantity();
        $quantity2 = $matchingApplication->getQuantity();
        $price1 = $application->getCost();
        $price2 = $matchingApplication->getCost();

        // Определяем количество для обмена (минимальное из двух количеств)
        $exchangeQuantity = min($quantity1, $quantity2);

        // Определяем цену (средняя цена)
        $averagePrice = ($price1 + $price2) / 2;

        // Находим Depositary для покупателя и продавца
        $depositary1 = $this->entityManager->getRepository(Depositary::class)->findOneBy([
            'portfolio' => $portfolio1,
            'stock' => $stock,
        ]);
        $depositary2 = $this->entityManager->getRepository(Depositary::class)->findOneBy([
            'portfolio' => $portfolio2,
            'stock' => $stock,
        ]);

        if ($application->getAction() === 'buy') {
            // Проверяем, достаточно ли средств у покупателя
            if ($portfolio1->getAvailableCash() < ($exchangeQuantity * $averagePrice)) {
                throw new \Exception('Not enough cash for the purchase.');
            }

            // Проверяем, достаточно ли акций у продавца
            if (!$depositary2 || $depositary2->getQuantity() < $exchangeQuantity) {
                throw new \Exception('Seller does not have enough stocks to sell.');
            }

            // Уменьшаем баланс покупателя
            $portfolio1->deductCash($exchangeQuantity * $averagePrice);
            // Увеличиваем количество акций у покупателя
            if (!$depositary1) {
                $depositary1 = new Depositary();
                $depositary1->setPortfolio($portfolio1);
                $depositary1->setStock($stock);
                $depositary1->setQuantity(0);
            }
            $depositary1->setQuantity($depositary1->getQuantity() + $exchangeQuantity);
            $this->entityManager->persist($depositary1);

            // Увеличиваем баланс продавца
            $portfolio2->addCash($exchangeQuantity * $averagePrice);
            // Уменьшаем количество акций у продавца
            if ($depositary2) {
                $depositary2->setQuantity($depositary2->getQuantity() - $exchangeQuantity);
                $this->entityManager->persist($depositary2);
            }
        } else {
            // Проверяем, достаточно ли акций у продавца
            if (!$depositary1 || $depositary1->getQuantity() < $exchangeQuantity) {
                throw new \Exception('Buyer does not have enough stocks to sell.');
            }

            // Уменьшаем баланс продавца
            $portfolio1->deductCash($exchangeQuantity * $averagePrice);
            // Уменьшаем количество акций у продавца
            $depositary1->setQuantity($depositary1->getQuantity() - $exchangeQuantity);
            $this->entityManager->persist($depositary1);

            // Увеличиваем баланс покупателя
            $portfolio2->addCash($exchangeQuantity * $averagePrice);
            // Увеличиваем количество акций у покупателя
            if (!$depositary2) {
                $depositary2 = new Depositary();
                $depositary2->setPortfolio($portfolio2);
                $depositary2->setStock($stock);
                $depositary2->setQuantity(0);
            }
            $depositary2->setQuantity($depositary2->getQuantity() + $exchangeQuantity);
            $this->entityManager->persist($depositary2);
        }

        // Удаляем заявки
        $this->entityManager->remove($application);
        $this->entityManager->remove($matchingApplication);
        $this->entityManager->flush();
    }
}