<?php

namespace App\Markdown;

use League\CommonMark\Normalizer\TextNormalizerInterface;

/**
 * Slug normalizer custom pour CommonMark v2.x.
 *
 * En v2.x, le SlugNormalizer natif preserve les accents (utile pour les
 * ideogrammes CJK par exemple). Pour notre README en francais, on veut
 * les retirer pour avoir des slugs propres : 'Pré-requis' → 'pre-requis'.
 *
 * Strategie :
 *   1. Transliterator retire les accents (NFD + strip combining marks)
 *   2. lowercase
 *   3. remplace tout non [a-z0-9-] par -
 *   4. dedoublonne et trim les -
 *
 * Resultat : 'Pré-requis' → 'pre-requis', 'Café crème' → 'cafe-creme'.
 */
class CustomSlugNormalizer implements TextNormalizerInterface
{
    public function normalize(string $text, array $context = []): string
    {
        // 1. Retire les accents via Transliterator (NFD decompose 'é' en 'e' + accent,
        // puis on retire les marks).
        $text = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC')
            ?->transliterate($text) ?? $text;

        // 2. Lowercase (UTF-8 safe)
        $text = mb_strtolower($text, 'UTF-8');

        // 3. Remplace tout caractere non alphanumerique par un tiret
        // Apres retrait des accents, [a-z0-9-] est suffisant.
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        // 4. Dedoublonne les '-' consecutifs
        $text = preg_replace('/-+/', '-', $text) ?? '';

        // 5. Trim les '-' en debut/fin
        return trim($text, '-');
    }
}