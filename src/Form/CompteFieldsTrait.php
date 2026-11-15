<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champs communs aux deux formulaires d'inscription.
 * Les champs du compte (e-mail, mot de passe) ne sont pas liés à l'entité
 * Commerce/Association : le contrôleur s'en sert pour créer le User.
 */
trait CompteFieldsTrait
{
    private function ajouterAdresse(FormBuilderInterface $builder): void
    {
        $builder
            ->add('adresse', TextType::class, ['label' => 'Adresse'])
            ->add('codePostal', TextType::class, ['label' => 'Code postal'])
            ->add('ville', TextType::class, ['label' => 'Ville']);
    }

    private function ajouterCompte(FormBuilderInterface $builder): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'adresse e-mail est obligatoire.'),
                    new Assert\Email(message: 'Cette adresse e-mail n\'est pas valide.'),
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'mapped' => false,
                'required' => false,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le mot de passe est obligatoire.'),
                    new Assert\Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
                ],
            ]);
    }
}
