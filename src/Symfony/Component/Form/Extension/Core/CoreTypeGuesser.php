<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core;

use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Util\ClassReflectionUtil;

/**
 * @author Andrew Moore <me@andrewmoore.ca>
 */
class CoreTypeGuesser implements FormTypeGuesserInterface
{
    private static $nameToTypeMapping = array(
        'submit' => 'submit',
        'reset' => 'reset',
    );

    /**
     * {@inheritDoc}
     */
    public function guessType($class, $property)
    {
        // Guess types for specialized button types
        if (!array_key_exists($property, self::$nameToTypeMapping)) {
            return null;
        }

        if (!ClassReflectionUtil::hasPropertyAvailable($class, $property)) {
            return new TypeGuess(self::$nameToTypeMapping[$property], array(), Guess::LOW_CONFIDENCE);
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function guessRequired($class, $property)
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function guessMaxLength($class, $property)
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function guessPattern($class, $property)
    {
        return null;
    }
}
