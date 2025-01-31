<?php

namespace App\Controller;

use App\Entity\Application;
use App\Entity\Depositary;
use App\Entity\PortfolioStock;
use App\Form\ApplicationType;
use App\Repository\ApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\OptimisticLockException;

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
    public function create(Request $request, EntityManagerInterface $entityManager): Response
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
                // Логика для покупки
                $requiredCash = $quantity * $cost;
                $availableCash = $portfolio->getAvailableCash();

                if ($availableCash < $requiredCash) {
                    $form->get('quantity')->addError(new FormError('Not enough available cash.'));
                    return $this->render('application/create.html.twig', ['form' => $form->createView()]);
                }

                // Замораживаем средства
                $portfolio->deductCash($requiredCash);
            } else { // Action is 'sell'
                // Получаем Depositary для данного портфеля и акции
                $depositary = $entityManager->getRepository(Depositary::class)
                    ->findOneBy(['portfolio' => $portfolio, 'stock' => $stock]);

                // Проверяем доступное количество акций (quantity - frozen)
                if (!$depositary || $depositary->getAvailableQuantity() < $quantity) {
                    $form->get('quantity')->addError(new FormError('Not enough available stocks.'));
                    return $this->render('application/create.html.twig', ['form' => $form->createView()]);
                }

                // Замораживаем акции
                $depositary->setFrozen($depositary->getFrozen() + $quantity);

                // Уменьшаем количество акций в портфеле
                $depositary->setQuantity($depositary->getQuantity() - $quantity);

                $entityManager->persist($depositary);
            }

            // Сохраняем заявку
            $entityManager->persist($application);
            $entityManager->flush();

            $this->addFlash('success', 'Application created successfully.');
            return $this->redirectToRoute('app_glass', ['stock_id' => $stock->getId()]);
        }

        return $this->render('application/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/application/update/{id}', name: 'app_application_update', methods: ['GET', 'PUT', 'POST'])]
    public function update(int $id, Request $request, EntityManagerInterface $entityManager, ApplicationRepository $applicationRepository): Response
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
                // Логика для обновления покупки
                $originalRequiredCash = $originalQuantity * $originalCost;
                $newRequiredCash = $newQuantity * $newCost;
                $difference = $newRequiredCash - $originalRequiredCash;

                $availableCash = $portfolio->getAvailableCash() + $originalRequiredCash; // Возвращаем оригинальные замороженные средства

                if ($difference > $availableCash) {
                    $form->get('quantity')->addError(new FormError('Not enough available cash.'));
                    return $this->render('application/update.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                // Замораживаем новые средства
                $portfolio->deductCash($difference);
            } else { // Action is 'sell'
                $depositary = $entityManager->getRepository(Depositary::class)
                    ->findOneBy(['portfolio' => $portfolio, 'stock' => $stock]);

                if (!$depositary) {
                    $form->get('stock')->addError(new FormError('Portfolio does not contain this stock.'));
                    return $this->render('application/update.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                // Возвращаем оригинальные замороженные акции
                $depositary->setFrozen($depositary->getFrozen() - $originalQuantity);

                // Увеличиваем количество акций в портфеле
                $depositary->setQuantity($depositary->getQuantity() + $originalQuantity);

                // Проверяем доступное количество акций
                $availableStocks = $depositary->getAvailableQuantity();

                if ($newQuantity > $availableStocks) {
                    $form->get('quantity')->addError(new FormError('Not enough available stocks.'));
                    return $this->render('application/update.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                // Замораживаем новое количество акций
                $depositary->setFrozen($depositary->getFrozen() + $newQuantity);

                // Уменьшаем количество акций в портфеле
                $depositary->setQuantity($depositary->getQuantity() - $newQuantity);

                $entityManager->persist($depositary);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Application updated successfully.');
            return $this->redirectToRoute('app_glass', ['stock_id' => $application->getStock()->getId()]);
        }

        return $this->render('application/update.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // DELETE: Удаление заявки
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
            // Возвращаем замороженные средства
            $frozenCash = $quantity * $cost;
            $portfolio->revertCashDeduction($frozenCash);
        } else { // Action is 'sell'
            // Возвращаем замороженные акции
            $depositary = $entityManager->getRepository(Depositary::class)
                ->findOneBy(['portfolio' => $portfolio, 'stock' => $stock]);

            if ($depositary) {
                $depositary->setFrozen($depositary->getFrozen() - $quantity);
                $entityManager->persist($depositary);
            }
        }

        // Удаляем заявку
        $entityManager->remove($application);
        $entityManager->flush();

        $this->addFlash('success', 'Application deleted successfully.');
        return $this->redirectToRoute('app_glass', ['stock_id' => $stock->getId()]);
    }
}