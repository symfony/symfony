<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Workflow\DependencyInjection;

use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Exception\RuntimeException;
use Symphony\Component\Workflow\Validator\StateMachineValidator;
use Symphony\Component\Workflow\Validator\WorkflowValidator;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ValidateWorkflowsPass implements CompilerPassInterface
{
    private $definitionTag;

    public function __construct(string $definitionTag = 'workflow.definition')
    {
        $this->definitionTag = $definitionTag;
    }

    public function process(ContainerBuilder $container)
    {
        $taggedServices = $container->findTaggedServiceIds($this->definitionTag, true);
        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $tag) {
                if (!array_key_exists('name', $tag)) {
                    throw new RuntimeException(sprintf('The "name" for the tag "%s" of service "%s" must be set.', $this->definitionTag, $id));
                }
                if (!array_key_exists('type', $tag)) {
                    throw new RuntimeException(sprintf('The "type" for the tag "%s" of service "%s" must be set.', $this->definitionTag, $id));
                }
                if (!array_key_exists('marking_store', $tag)) {
                    throw new RuntimeException(sprintf('The "marking_store" for the tag "%s" of service "%s" must be set.', $this->definitionTag, $id));
                }

                $this->createValidator($tag)->validate($container->get($id), $tag['name']);
            }
        }
    }

    private function createValidator($tag)
    {
        if ('state_machine' === $tag['type']) {
            return new StateMachineValidator();
        }

        if ('single_state' === $tag['marking_store']) {
            return new WorkflowValidator(true);
        }

        return new WorkflowValidator();
    }
}
