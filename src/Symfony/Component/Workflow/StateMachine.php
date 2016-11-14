<?php

namespace Symfony\Component\Workflow;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Exception\InvalidArgumentException;
use Symfony\Component\Workflow\MarkingStore\MarkingStore;
use Symfony\Component\Workflow\MarkingStore\PropertyAccessMarkingStore;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Jules Pietri <jules@heahprod.com>
 */
class StateMachine extends Workflow
{
    public function __construct(Definition $definition, MarkingStore $markingStore = null, EventDispatcherInterface $dispatcher = null, $name = 'unnamed')
    {
        if (null !== $markingStore && Marking::STRATEGY_SINGLE_STATE !== $strategy = $markingStore->getStrategy()) {
            throw new InvalidArgumentException(sprintf('"%s" class only supports strategy "%s" for marking, but got "%s". Consider using the "%s" class instead.', __CLASS__, Marking::STRATEGY_SINGLE_STATE, $strategy, parent::class));
        }

        $markingStore = $markingStore ?: new PropertyAccessMarkingStore('marking', null, Marking::STRATEGY_SINGLE_STATE);

        parent::__construct($definition, $markingStore, $dispatcher, $name);
    }
}
