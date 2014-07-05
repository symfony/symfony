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
 * Keeps the state of the cursor when asking a choice question.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class ChoicesCursor
{
    private $totalChoices;
    private $position;
    private $hasMoved = false;

    /**
     * Creates a new cursor.
     *
     * @param int      $totalChoices The total number of choices
     * @param int|null $position     The cursor position (default to the last choice)
     */
    public function __construct($totalChoices, $position = null)
    {
        $this->totalChoices = $totalChoices;
        $this->position = null === $position ? $this->totalChoices - 1 : $position;
    }

    /**
     * Tells if the cursor has moved by calling a move*() method.
     *
     * @return bool
     */
    public function hasMoved()
    {
        return $this->hasMoved;
    }

    /**
     * Moves the cursor at the position and returns the diff.
     *
     * @param int $position The new position
     *
     * @return int x if the cursor must move up x lines
     *                 -x if the cursor must move down x lines
     */
    public function moveAt($position)
    {
        if ($position === $this->position) {
            return 0;
        }

        $diff = $this->position - $position;

        $this->position = $position;
        $this->hasMoved = true;

        return $diff;
    }

    /**
     * Moves the cursor up one choice and returns the diff.
     *
     * @return int x if the cursor must move up x lines
     *                 -x if the cursor must move down x lines
     */
    public function moveUp()
    {
        if ($this->position === 0) {
            $newPosition = $this->totalChoices - 1;
        } else {
            $newPosition = $this->position - 1;
        }

        return $this->moveAt($newPosition);
    }

    /**
     * Moves the cursor up one choice and returns the diff.
     *
     * @return int x if the cursor must move up x lines
     *                 -x if the cursor must move down x lines
     */
    public function moveDown()
    {
        if ($this->position === $this->totalChoices - 1) {
            $newPosition = 0;
        } else {
            $newPosition = $this->position + 1;
        }

        return $this->moveAt($newPosition);
    }

    /**
     * Gets the current position.
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }
}
