<?php

namespace App\Controller;

use App\Entity\Depositary;
use App\Entity\Portfolio;
use App\Entity\PortfolioStock;
use App\Entity\User;
use App\Form\AddStockToPortfolioType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(EntityManagerInterface $entityManager, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $totalBalance = 0;
        foreach ($user->getPortfolios() as $portfolio) {
            $totalBalance += $portfolio->getBalance();
        }

        $form = $this->createForm(AddStockToPortfolioType::class, null, [
            'user' => $user,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $stock = $data['stock'];
            $quantity = $data['quantity'];
            $portfolio = $data['portfolio'];

            if ($portfolio->getUser() !== $user) {
                $this->addFlash('error', 'Invalid portfolio selected.');
                return $this->redirectToRoute('app_profile');
            }

            // Update Depositary
            $existingDepositary = $entityManager->getRepository(Depositary::class)
                ->findOneBy(['portfolio' => $portfolio, 'stock' => $stock]);

            if ($existingDepositary) {
                $existingDepositary->setQuantity($existingDepositary->getQuantity() + $quantity);
            } else {
                $depositary = new Depositary();
                $depositary->setPortfolio($portfolio);
                $depositary->setStock($stock);
                $depositary->setQuantity($quantity);
                $entityManager->persist($depositary);
            }

            // Sync with PortfolioStock
            $portfolioStock = $entityManager->getRepository(PortfolioStock::class)
                ->findOneBy(['portfolio' => $portfolio, 'stock' => $stock]);

            if (!$portfolioStock) {
                $portfolioStock = new PortfolioStock();
                $portfolioStock->setPortfolio($portfolio);
                $portfolioStock->setStock($stock);
                $portfolioStock->setQuantity(0);
            }

            $portfolioStock->setQuantity($portfolioStock->getQuantity() + $quantity);
            $entityManager->persist($portfolioStock);

            // Update portfolio balance
            $stockPrice = $stock->getCurrentPrice();
            $portfolio->setBalance($portfolio->getBalance() + ($stockPrice * $quantity));
            $entityManager->persist($portfolio);

            $entityManager->flush();
            $this->addFlash('success', 'Stock added to portfolio successfully.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'total_balance' => $totalBalance,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profile/create-portfolio', name: 'app_profile_create_portfolio', methods: ['POST'])]
    public function createPortfolio(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getPortfolios()->count() >= 5) {
            $this->addFlash('error', 'You cannot create more than 5 portfolios.');
            return $this->redirectToRoute('app_profile');
        }

        $portfolio = new Portfolio();
        $portfolio->setUser($user);
        $portfolio->setBalance(0);
        $portfolio->setCash(0);
        $portfolio->setFrozenCash(0);

        $entityManager->persist($portfolio);
        $entityManager->flush();

        $this->addFlash('success', 'New portfolio created successfully.');
        return $this->redirectToRoute('app_profile');
    }
}