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
use Symfony\Component\Workflow\Validator\SinglePlaceWorkflowValidator;
use Symfony\Component\Workflow\Validator\StateMachineValidator;
use Symfony\Component\Workflow\Validator\WorkflowValidator;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ValidateWorkflowsPass implements CompilerPassInterface
{
    /**
     * @var DefinitionValidatorInterface[]
     */
    private $validators = array();

    public function process(ContainerBuilder $container)
    {
        $taggedServices = $container->findTaggedServiceIds('workflow.definition');
        foreach ($taggedServices as $id => $tags) {
            $definition = $container->get($id);
            foreach ($tags as $tag) {
                if (empty($tag['name'])) {
                    throw new RuntimeException(sprintf('The "name" for the tag "workflow.definition" of service "%s" must be set.', $id));
                }
                if (empty($tag['type'])) {
                    throw new RuntimeException(sprintf('The "type" for the tag "workflow.definition" of service "%s" must be set.', $id));
                }
                if (empty($tag['marking_store'])) {
                    throw new RuntimeException(sprintf('The "marking_store" for the tag "workflow.definition" of service "%s" must be set.', $id));
                }

                $this->getValidator($tag)->validate($definition, $tag['name']);
            }
        }
    }

    /**
     * @param array $tag
     *
     * @return DefinitionValidatorInterface
     */
    private function getValidator($tag)
    {
        if ($tag['type'] === 'state_machine') {
            $name = 'state_machine';
            $class = StateMachineValidator::class;
        } elseif ($tag['marking_store'] === 'scalar') {
            $name = 'single_place';
            $class = SinglePlaceWorkflowValidator::class;
        } else {
            $name = 'workflow';
            $class = WorkflowValidator::class;
        }

        if (empty($this->validators[$name])) {
            $this->validators[$name] = new $class();
        }

        return $this->validators[$name];
    }
}
