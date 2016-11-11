<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\MarkingStore;

use Symfony\Component\Workflow\Exception\InvalidArgumentException;
use Symfony\Component\Workflow\Exception\InvalidMarkingException;
use Symfony\Component\Workflow\Exception\InvalidMarkingStrategyException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MultipleStateMarking;
use Symfony\Component\Workflow\SingleStateMarking;

/**
 * A MarkingStore is the interface between the Workflow Component and a
 * plain old PHP object: the subject.
 *
 * It converts the Marking into something understandable by the subject and vice
 * versa.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Jules Pietri <jules@heahprod.com>
 */
abstract class MarkingStore
{
    private $strategy;

    /**
     * @param string $strategy A Marking constant
     */
    public function __construct($strategy = Marking::STRATEGY_MULTIPLE_STATE)
    {
        $strategies = array(Marking::STRATEGY_SINGLE_STATE, Marking::STRATEGY_MULTIPLE_STATE);
        if (!in_array($strategy, $strategies, true)) {
            throw new InvalidArgumentException(sprintf('Marking strategy must be one of "%s", but got "%".', implode('" or "', $strategies), $strategy));
        }

        $this->strategy = $strategy;
    }

    /**
     * Returns the strategy used for marking.
     *
     * @return string A Marking constant
     */
    final public function getStrategy()
    {
        return $this->strategy;
    }

    /**
     * Gets a Marking from a subject.
     *
     * @param object $subject A subject
     *
     * @return Marking The marking
     *
     * @throws InvalidMarkingException         When the marking does not match the strategy
     * @throws InvalidMarkingStrategyException When the strategy in unknown
     */
    public function getMarking($subject)
    {
        if (Marking::STRATEGY_MULTIPLE_STATE === $this->strategy) {
            $marking = $this->getMultipleStateMarking($subject);

            if (!$marking instanceof MultipleStateMarking) {
                throw new InvalidMarkingException(MultipleStateMarking::class, $marking);
            }

            return $marking;
        }

        if (Marking::STRATEGY_SINGLE_STATE === $this->strategy) {
            $marking = $this->getSingleStateMarking($subject);

            if (!$marking instanceof SingleStateMarking) {
                throw new InvalidMarkingException(SingleStateMarking::class, $marking);
            }

            return $marking;
        }

        throw new InvalidMarkingStrategyException(static::class);
    }

    /**
     * Sets a Marking to a subject.
     *
     * Must return an
     *
     * @param object  $subject A subject
     * @param Marking $marking A marking
     *
     * @throws InvalidMarkingException         When the marking does not match the strategy
     * @throws InvalidMarkingStrategyException When the strategy in unknown
     */
    public function setMarking($subject, Marking $marking)
    {
        if (Marking::STRATEGY_MULTIPLE_STATE === $this->strategy) {
            if (!$marking instanceof MultipleStateMarking) {
                throw new InvalidMarkingException(MultipleStateMarking::class, $marking);
            }

            $this->setMultipleStateMarking($subject, $marking);

            return;
        }

        if (Marking::STRATEGY_SINGLE_STATE === $this->strategy) {
            if (!$marking instanceof SingleStateMarking) {
                throw new InvalidMarkingException(SingleStateMarking::class, $marking);
            }

            $this->setSingleStateMarking($subject, $marking);

            return;
        }

        throw new InvalidMarkingStrategyException(static::class);
    }

    /**
     * Gets a SingleStateMarking from a subject.
     *
     * @param object $subject A subject
     *
     * @return SingleStateMarking The marking
     */
    abstract protected function getSingleStateMarking($subject);

    /**
     * Sets a SingleStateMarking to a subject.
     *
     * @param object             $subject
     * @param SingleStateMarking $marking
     */
    abstract protected function setSingleStateMarking($subject, SingleStateMarking $marking);

    /**
     * Gets a MultipleStateMarking from a subject.
     *
     * @param object $subject A subject
     *
     * @return MultipleStateMarking The marking
     */
    abstract protected function getMultipleStateMarking($subject);

    /**
     * Sets a MultipleStateMarking to a subject.
     *
     * @param object               $subject
     * @param MultipleStateMarking $marking
     */
    abstract protected function setMultipleStateMarking($subject, MultipleStateMarking $marking);
}
