<?php

namespace App\Form;

use App\Entity\Association;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AssociationRegistrationType extends AbstractType
{
    use CompteFieldsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom de l\'association'])
            ->add('rna', TextType::class, ['label' => 'Numéro RNA', 'help' => 'W suivi de 9 chiffres']);

        $this->ajouterAdresse($builder);
        $this->ajouterCompte($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Association::class]);
    }
}
