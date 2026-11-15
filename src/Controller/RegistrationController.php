<?php

namespace App\Controller;

use App\Entity\Association;
use App\Entity\Commerce;
use App\Entity\User;
use App\Form\AssociationRegistrationType;
use App\Form\CommerceRegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/inscription/commerce', name: 'app_register_commerce')]
    public function commerce(Request $request): Response
    {
        $commerce = new Commerce();
        $form = $this->createForm(CommerceRegistrationType::class, $commerce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->emailDisponible($form)) {
            $commerce->setUser($this->creerUtilisateur($form, $commerce->getNom(), User::ROLE_COMMERCE));
            $this->em->persist($commerce);
            $this->em->flush();

            $this->addFlash('success', 'Votre compte commerce est créé. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/commerce.html.twig', ['form' => $form]);
    }

    #[Route('/inscription/association', name: 'app_register_association')]
    public function association(Request $request): Response
    {
        $association = new Association();
        $form = $this->createForm(AssociationRegistrationType::class, $association);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->emailDisponible($form)) {
            $association->setUser($this->creerUtilisateur($form, $association->getNom(), User::ROLE_ASSOCIATION));
            $this->em->persist($association);
            $this->em->flush();

            $this->addFlash('success', 'Votre compte est créé. Un administrateur doit le valider avant votre première réservation.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/association.html.twig', ['form' => $form]);
    }

    private function emailDisponible(FormInterface $form): bool
    {
        $email = mb_strtolower(trim((string) $form->get('email')->getData()));

        if (null !== $this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $form->get('email')->addError(new FormError('Un compte existe déjà avec cette adresse e-mail.'));

            return false;
        }

        return true;
    }

    private function creerUtilisateur(FormInterface $form, string $nom, string $role): User
    {
        $user = (new User())
            ->setEmail((string) $form->get('email')->getData())
            ->setNom($nom)
            ->setTelephone($form->get('telephone')->getData())
            ->setRoles([$role]);

        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));

        return $user;
    }
}
