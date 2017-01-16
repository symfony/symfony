<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\Validator\StateMachineValidator;
use Symfony\Component\Workflow\Validator\WorkflowValidator;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ValidateWorkflowsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $taggedServices = $container->findTaggedServiceIds('workflow.definition');
        foreach ($taggedServices as $id => $tags) {
            $definition = $container->get($id);
            foreach ($tags as $tag) {
                if (!array_key_exists('name', $tag)) {
                    throw new RuntimeException(sprintf('The "name" for the tag "workflow.definition" of service "%s" must be set.', $id));
                }
                if (!array_key_exists('type', $tag)) {
                    throw new RuntimeException(sprintf('The "type" for the tag "workflow.definition" of service "%s" must be set.', $id));
                }
                if (!array_key_exists('marking_store', $tag)) {
                    throw new RuntimeException(sprintf('The "marking_store" for the tag "workflow.definition" of service "%s" must be set.', $id));
                }

                $this->createValidator($tag)->validate($definition, $tag['name']);
            }
        }
    }

    /**
     * @param array $tag
     *
     * @return DefinitionValidatorInterface
     */
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
