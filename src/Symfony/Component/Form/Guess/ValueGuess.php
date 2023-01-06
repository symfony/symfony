<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Guess;

/**
 * Contains a guessed value.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValueGuess extends Guess
{
    private string|int|bool|null $value;

    /**
     * @param int $confidence The confidence that the guessed class name is correct
     */
    public function __construct(string|int|bool|null $value, int $confidence)
    {
        parent::__construct($confidence);

        $this->value = $value;
    }

    /**
     * Returns the guessed value.
     */
    public function getValue(): string|int|bool|null
    {
        return $this->value;
    }
}
