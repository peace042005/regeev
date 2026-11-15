<?php

namespace App\Service;

/**
 * Génère le code que l'association présente au commerce lors du retrait.
 * J'ai retiré les caractères ambigus (0/O, 1/I/L) pour éviter les erreurs de lecture.
 */
class CodeRetraitGenerator
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    public const LONGUEUR = 6;

    public function generer(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LONGUEUR; ++$i) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
