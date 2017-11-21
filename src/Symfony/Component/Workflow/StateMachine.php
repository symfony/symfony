<?php

namespace Symfony\Component\Workflow;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\SingleStateMarkingStore;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class StateMachine extends Workflow
{
    public function __construct(Definition $definition, MarkingStoreInterface $markingStore = null, EventDispatcherInterface $dispatcher = null, string $name = 'unnamed')
    {
        parent::__construct($definition, $markingStore ?: new SingleStateMarkingStore(), $dispatcher, $name);
    }
}
