<?php

namespace Symfony\Component\Workflow;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\ScalarMarkingStore;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class StateMachine extends Workflow
{
    public function __construct(DefinitionInterface $definition, MarkingStoreInterface $markingStore = null, EventDispatcherInterface $dispatcher = null, $name = 'unnamed')
    {
        parent::__construct($definition, $markingStore ?: new ScalarMarkingStore(), $dispatcher, $name);
    }
}
