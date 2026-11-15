<?php

namespace App\Form;

use App\Entity\Commerce;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommerceRegistrationType extends AbstractType
{
    use CompteFieldsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom du commerce'])
            ->add('siret', TextType::class, ['label' => 'Numéro de SIRET', 'help' => '14 chiffres']);

        $this->ajouterAdresse($builder);
        $this->ajouterCompte($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Commerce::class]);
    }
}
