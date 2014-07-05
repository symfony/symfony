<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Question;

/**
 * Provides access to the choices data.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class ChoicesMap
{
    private $choices;
    private $positionValueMap;
    private $valuePositionMap;

    /**
     * Creates a new choices map.
     *
     * @param array $choices The question choices
     */
    public function __construct(array $choices)
    {
        $this->choices = $choices;
        $this->positionValueMap = array_keys($choices);
        $this->valuePositionMap = array_flip($this->positionValueMap);
    }

    /**
     * Gets the choice position from its value.
     *
     * @param mixed $value
     *
     * @return int|null
     */
    public function getChoicePositionFromValue($value)
    {
        return isset($this->valuePositionMap[$value]) ? $this->valuePositionMap[$value] : null;
    }

    /**
     * Gets the choice text from its value.
     *
     * @param mixed $value
     *
     * @return string
     */
    public function getChoiceTextFromValue($value)
    {
        return isset($this->choices[$value]) ? $this->choices[$value] : '';
    }

    /**
     * Gets the text of the choice at the given position.
     *
     * @param int $position
     *
     * @return string
     */
    public function getChoiceTextAt($position)
    {
        return $this->getChoiceTextFromValue($this->getChoiceValueAt($position));
    }

    /**
     * Gets the value of the choice at the given position.
     *
     * @param int $position
     *
     * @return mixed
     */
    public function getChoiceValueAt($position)
    {
        return isset($this->positionValueMap[$position]) ? $this->positionValueMap[$position] : null;
    }
}
