<?php

namespace App\Controller;

use App\Entity\Application;
use App\Entity\Depositary;
use App\Entity\PortfolioStock;
use App\Form\ApplicationType;
use App\Repository\ApplicationRepository;
use App\Service\DealService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApplicationController extends AbstractController
{
    // READ: Просмотр всех заявок по конкретной ценной бумаге (биржевой стакан)
    #[Route('/glass/{stock_id}', name: 'app_glass', methods: ['GET'])]
    public function glass(int $stock_id, ApplicationRepository $applicationRepository): Response
    {
        $applications = $applicationRepository->findBy(['stock' => $stock_id]);

        return $this->render('application/glass.html.twig', [
            'applications' => $applications,
        ]);
    }

    // CREATE: Создание новой заявки
    #[Route('/application/create', name: 'app_application_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, DealService $dealService): Response
    {
        $application = new Application();
        $form = $this->createForm(ApplicationType::class, $application, [
            'user' => $this->getUser()
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            $portfolio = $application->getPortfolio();
            $stock = $application->getStock();
            $quantity = $application->getQuantity();
            $cost = $application->getCost();
            $action = $application->getAction();

            if ($portfolio->getUser() !== $user) {
                $this->addFlash('error', 'You do not own this portfolio.');
                return $this->redirectToRoute('app_application_create');
            }

            if ($action === 'buy') {
                $requiredCash = $quantity * $cost;
                $availableCash = $portfolio->getAvailableCash();

                $this->addFlash('debug', 'Available cash: ' . $availableCash);
                $this->addFlash('debug', 'Required cash: ' . $requiredCash);

                if ($availableCash < $requiredCash) {
                    $form->get('quantity')->addError(new FormError('Not enough available cash.'));
                    return $this->render('application/create.html.twig', ['form' => $form->createView()]);
                }

                $portfolio->deductCash($requiredCash);
            } else { // Action is 'sell'
                // Получаем Depositary для данного портфеля и акции
                $depositary = $entityManager->getRepository(Depositary::class)
                    ->findOneBy(['portfolio' => $portfolio, 'stock' => $stock]);

                if (!$depositary || $depositary->getQuantity() < $quantity) {
                    $form->get('quantity')->addError(new FormError('Not enough available stocks.'));
                    return $this->render('application/create.html.twig', ['form' => $form->createView()]);
                }

                $this->addFlash('debug', 'Available stocks: ' . $depositary->getQuantity());
                $this->addFlash('debug', 'Required stocks: ' . $quantity);

                // Обновляем количество акций в Depositary
                $depositary->setQuantity($depositary->getQuantity() - $quantity);
                $entityManager->persist($depositary);
            }

            // Проверяем наличие подходящей заявки
            $matchingApplication = $dealService->findMatchingApplication($application);
            if ($matchingApplication) {
                $dealService->executeTrade($application, $matchingApplication);
                $this->addFlash('success', 'Trade executed successfully.');
                return $this->redirectToRoute('app_glass', ['stock_id' => $stock->getId()]);
            }

            // Persist the application
            $entityManager->persist($application);
            $entityManager->flush();

            $this->addFlash('success', 'Application created successfully.');
            return $this->redirectToRoute('app_glass', ['stock_id' => $stock->getId()]);
        }

        return $this->render('application/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // UPDATE: Обновление заявки
    #[Route('/application/update/{id}', name: 'app_application_update', methods: ['GET', 'PUT', 'POST'])]
    public function update(int $id, Request $request, EntityManagerInterface $entityManager, ApplicationRepository $applicationRepository, DealService $dealService): Response
    {
        $application = $applicationRepository->find($id);
        if (!$application) {
            throw $this->createNotFoundException('Application not found');
        }

        $user = $this->getUser();
        $portfolio = $application->getPortfolio();
        if ($portfolio->getUser() !== $user) {
            throw $this->createAccessDeniedException('You do not own this portfolio.');
        }

        $originalQuantity = $application->getQuantity();
        $originalCost = $application->getCost();
        $originalAction = $application->getAction();

        $form = $this->createForm(ApplicationType::class, $application, [
            'user' => $user,
            'method' => 'PUT',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newQuantity = $application->getQuantity();
            $newCost = $application->getCost();
            $newAction = $application->getAction();

            if ($originalAction !== $newAction) {
                $form->addError(new FormError('Changing action is not allowed.'));
                return $this->render('application/update.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $stock = $application->getStock();

            if ($originalAction === 'buy') {
                $originalRequiredCash = $originalQuantity * $originalCost;
                $newRequiredCash = $newQuantity * $newCost;
                $difference = $newRequiredCash - $originalRequiredCash;

                $availableCash = $portfolio->getAvailableCash() + $originalRequiredCash; // Revert original frozen cash

                if ($difference > $availableCash) {
                    $form->get('quantity')->addError(new FormError('Not enough available cash.'));
                    return $this->render('application/update.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                // Deduct the new required cash
                $portfolio->deductCash($difference);
            } else { // Action is 'sell'
                // Получаем Depositary для данного портфеля и акции
                $depositary = $entityManager->getRepository(Depositary::class)
                    ->findOneBy(['portfolio' => $portfolio, 'stock' => $stock]);

                if (!$depositary) {
                    $form->get('stock')->addError(new FormError('Portfolio does not contain this stock.'));
                    return $this->render('application/update.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                $originalFrozen = $originalQuantity;
                $newFrozen = $newQuantity;
                $difference = $newFrozen - $originalFrozen;

                $availableStocks = $depositary->getQuantity() + $originalFrozen; // Revert original frozen stocks

                if ($difference > $availableStocks) {
                    $form->get('quantity')->addError(new FormError('Not enough available stocks.'));
                    return $this->render('application/update.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                // Обновляем количество акций в Depositary
                $depositary->setQuantity($depositary->getQuantity() - $difference);
                $entityManager->persist($depositary);

                // Обновляем PortfolioStock
                $portfolioStock = $entityManager->getRepository(PortfolioStock::class)->findOneBy([
                    'portfolio' => $portfolio,
                    'stock' => $stock,
                ]);

                if ($portfolioStock) {
                    $portfolioStock->setFrozen($portfolioStock->getFrozen() + $difference);
                    $entityManager->persist($portfolioStock);
                }
            }

            // Проверяем наличие подходящей заявки
            $matchingApplication = $dealService->findMatchingApplication($application);
            if ($matchingApplication) {
                $dealService->executeTrade($application, $matchingApplication);
                $this->addFlash('success', 'Trade executed successfully.');
                return $this->redirectToRoute('app_glass', ['stock_id' => $application->getStock()->getId()]);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Application updated successfully.');

            return $this->redirectToRoute('app_glass', ['stock_id' => $application->getStock()->getId()]);
        }

        return $this->render('application/update.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/application/delete/{id}', name: 'app_application_delete', methods: ['POST', 'DELETE'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager, ApplicationRepository $applicationRepository): Response
    {
        $application = $applicationRepository->find($id);
        if (!$application) {
            throw $this->createNotFoundException('Application not found.');
        }

        $user = $this->getUser();
        $portfolio = $application->getPortfolio();
        if ($portfolio->getUser() !== $user) {
            throw $this->createAccessDeniedException('You do not own this portfolio.');
        }

        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $id, $submittedToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $action = $application->getAction();
        $quantity = $application->getQuantity();
        $cost = $application->getCost();
        $stock = $application->getStock();

        if ($action === 'buy') {
            $frozenCash = $quantity * $cost;
            $portfolio->revertCashDeduction($frozenCash);
        } else { // Action is 'sell'
            // Возвращаем акции в Depositary
            $depositary = $entityManager->getRepository(Depositary::class)
                ->findOneBy(['portfolio' => $portfolio, 'stock' => $stock]);

            if ($depositary) {
                $depositary->setQuantity($depositary->getQuantity() + $quantity);
                $entityManager->persist($depositary);
            } else {
                // Если Depositary не существует, создаем новый
                $depositary = new Depositary();
                $depositary->setPortfolio($portfolio);
                $depositary->setStock($stock);
                $depositary->setQuantity($quantity);
                $entityManager->persist($depositary);
            }

            // Уменьшаем количество замороженных акций в PortfolioStock
            $portfolioStock = $entityManager->getRepository(PortfolioStock::class)->findOneBy([
                'portfolio' => $portfolio,
                'stock' => $stock,
            ]);

            if ($portfolioStock) {
                $portfolioStock->setFrozen($portfolioStock->getFrozen() - $quantity);
                $entityManager->persist($portfolioStock);
            }
        }

        // Удаляем заявку
        $entityManager->remove($application);
        $entityManager->flush();

        $this->addFlash('success', 'Application deleted successfully.');
        return $this->redirectToRoute('app_glass', ['stock_id' => $stock->getId()]);
    }
}