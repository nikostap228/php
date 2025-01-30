<?php

namespace App\Form;

use App\Entity\Portfolio;
use App\Entity\Stock;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddStockToPortfolioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('stock', EntityType::class, [
                'class' => Stock::class, // Сущность Stock
                'choice_label' => 'ticker', // Поле, которое будет отображаться в выпадающем списке
                'label' => 'Select Stock',
                'placeholder' => 'Choose a stock', // Подсказка в выпадающем списке
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantity',
                'attr' => ['min' => 1],
            ])
            ->add('stock', EntityType::class, [
            'class' => 'App\Entity\Stock',
            'choice_label' => 'ticker',
            ])
            ->add('quantity', IntegerType::class)
            ->add('portfolio', EntityType::class, [
                'class' => Portfolio::class,
                'choice_label' => 'id',
                'choices' => $options['user']->getPortfolios(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null, // Форма не привязана к конкретной сущности
            'user' => null,
        ]);
    }
}