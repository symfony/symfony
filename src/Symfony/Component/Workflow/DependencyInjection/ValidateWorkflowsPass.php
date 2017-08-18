<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\Workflow\Validator\StateMachineValidator;
use Symfony\Component\Workflow\Validator\WorkflowValidator;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ValidateWorkflowsPass implements CompilerPassInterface
{
    private $definitionTag;

    public function __construct($definitionTag = 'workflow.definition')
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
