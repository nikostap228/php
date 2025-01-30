<?php

namespace App\Form;

use App\Entity\Application;
use App\Entity\Portfolio;
use App\Entity\Stock;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class ApplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'];
        $application = $builder->getData(); // Get the Application entity
        $isEdit = $application && $application->getId();

        $builder
            ->add('portfolio', EntityType::class, [
                'class' => Portfolio::class,
                'choice_label' => 'id',
                'choices' => $user ? $user->getPortfolios() : [],
                'label' => 'Portfolio',
                'invalid_message' => 'Invalid portfolio',
                'placeholder' => 'Select a portfolio',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select a portfolio.',
                    ]),
                ],
                // Pre-select the current portfolio when editing
                'data' => $application->getPortfolio() ?? null,
            ])
            ->add('stock', EntityType::class, [
                'class' => Stock::class,
                'choice_label' => 'ticker', // Consider using 'name' for better readability
                'label' => 'Stock',
                'invalid_message' => 'Invalid stock',
                'placeholder' => 'Select a stock',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select a stock.',
                    ]),
                ],
            ])
            ->add('quantity', NumberType::class, [
                'label' => 'Quantity',
                'html5' => true,
                'attr' => ['min' => 1],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a quantity.',
                    ]),
                    new GreaterThanOrEqual([
                        'value' => 1,
                        'message' => 'Quantity must be at least 1.',
                    ]),
                ],
            ])
            ->add('cost', NumberType::class, [
                'label' => 'Cost per unit',
                'html5' => true,
                'attr' => ['min' => 0.01, 'step' => 0.01],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a cost per unit.',
                    ]),
                    new GreaterThanOrEqual([
                        'value' => 0.01,
                        'message' => 'Cost per unit must be at least 0.01.',
                    ]),
                ],
            ]);

        if (!$isEdit) {
            $builder->add('action', ChoiceType::class, [
                'choices' => [
                    'Buy' => 'buy',
                    'Sell' => 'sell',
                ],
                'label' => 'Action',
                'expanded' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select an action.',
                    ]),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Application::class,
            'user' => null,
        ]);

        // Ensure that the 'user' option is passed when creating the form
        $resolver->setRequired('user');
        $resolver->setAllowedTypes('user', ['null', 'object']);
    }
}