<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * Optional extension for form type guessers that support minimum lengths.
 *
 * Implementing this interface is not required for existing type guessers.
 */
interface MinLengthTypeGuesserInterface extends FormTypeGuesserInterface
{
    /**
     * Returns a guess about the field's minimum length.
     */
    public function guessMinLength(string $class, string $property): ?Guess\ValueGuess;
}
