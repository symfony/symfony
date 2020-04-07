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

use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Marking;

/**
 * MethodMarkingStore stores the marking with a subject's method.
 *
 * This store deals with a "single state" or "multiple state" Marking.
 *
 * "single state" Marking means a subject can be in one and only one state at
 * the same time. Use it with state machine.
 *
 * "multiple state" Marking means a subject can be in many states at the same
 * time. Use it with workflow.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class MethodMarkingStore implements MarkingStoreInterface
{
    private $singleState;
    private $property;

    /**
     * @param string $property Used to determine methods to call
     *                         The `getMarking` method will use `$subject->getProperty()`
     *                         The `setMarking` method will use `$subject->setProperty(string|array $places, array $context = array())`
     */
    public function __construct(bool $singleState = false, string $property = 'marking')
    {
        $this->singleState = $singleState;
        $this->property = $property;
    }

    /**
     * {@inheritdoc}
     */
    public function getMarking($subject): Marking
    {
        $method = 'get'.ucfirst($this->property);

        if (!method_exists($subject, $method)) {
            throw new LogicException(sprintf('The method "%s::%s()" does not exist.', \get_class($subject), $method));
        }

        $marking = $subject->{$method}();

        if (null === $marking) {
            return new Marking();
        }

        if ($this->singleState) {
            $marking = [(string) $marking => 1];
        }

        return new Marking($marking);
    }

    /**
     * {@inheritdoc}
     */
    public function setMarking($subject, Marking $marking, array $context = [])
    {
        $marking = $marking->getPlaces();

        if ($this->singleState) {
            $marking = key($marking);
        }

        $method = 'set'.ucfirst($this->property);

        if (!method_exists($subject, $method)) {
            throw new LogicException(sprintf('The method "%s::%s()" does not exist.', \get_class($subject), $method));
        }

        $subject->{$method}($marking, $context);
    }
}
