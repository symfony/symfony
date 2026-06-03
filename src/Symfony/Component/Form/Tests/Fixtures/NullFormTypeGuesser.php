<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

class NullFormTypeGuesser implements FormTypeGuesserInterface
{
    public function guessType($class, $property): ?TypeGuess
    {
        return null;
    }

    public function guessRequired($class, $property): ?ValueGuess
    {
        return null;
    }

    public function guessMaxLength($class, $property): ?ValueGuess
    {
        return null;
    }

    public function guessPattern($class, $property): ?ValueGuess
    {
        return null;
    }
}
