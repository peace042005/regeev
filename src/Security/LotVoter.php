<?php

namespace App\Security;

use App\Entity\Lot;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Décide si la personne connectée a le droit d'agir sur un lot.
 * Un commerce ne voit et ne gère que ses propres lots,
 * et il ne peut modifier un lot que tant qu'il est en brouillon.
 *
 * @extends Voter<string, Lot>
 */
class LotVoter extends Voter
{
    public const GERER = 'LOT_GERER';
    public const MODIFIER = 'LOT_MODIFIER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::GERER, self::MODIFIER], true) && $subject instanceof Lot;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $proprietaire = $subject->getCommerce()?->getUser();
        if (null === $proprietaire || $proprietaire->getId() !== $user->getId()) {
            return false;
        }

        return match ($attribute) {
            self::GERER => true,
            self::MODIFIER => Lot::STATUT_BROUILLON === $subject->getStatut(),
            default => false,
        };
    }
}
