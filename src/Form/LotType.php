<?php

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Lot;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, ['label' => 'Titre du lot'])
            ->add('categorie', EntityType::class, [
                'class' => Categorie::class,
                'choice_label' => 'nom',
                'label' => 'Catégorie',
                'placeholder' => 'Choisir une catégorie',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('quantite', NumberType::class, [
                'label' => 'Quantité',
                'html5' => true,
                'attr' => ['step' => '0.1', 'min' => '0.1'],
            ])
            ->add('unite', ChoiceType::class, [
                'label' => 'Unité',
                'choices' => ['kg' => 'kg', 'unités' => 'unités', 'colis' => 'colis', 'litres' => 'litres'],
            ])
            ->add('poidsKg', NumberType::class, [
                'label' => 'Poids estimé (kg)',
                'help' => 'Sert à calculer les kilos de nourriture sauvés.',
                'html5' => true,
                'attr' => ['step' => '0.1', 'min' => '0.1'],
            ])
            ->add('dateLimite', DateType::class, [
                'label' => 'Date limite de consommation',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('retraitDebut', DateTimeType::class, [
                'label' => 'Retrait possible à partir de',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('retraitFin', DateTimeType::class, [
                'label' => 'Retrait possible jusqu\'à',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Lot::class]);
    }
}
