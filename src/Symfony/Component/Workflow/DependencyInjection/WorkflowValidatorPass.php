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

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class WorkflowValidatorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('workflow') as $attributes) {
            foreach ($attributes as $attribute) {
                foreach ($attribute['definition_validators'] ?? [] as $validatorClass) {
                    $container->addResource(new FileResource($container->getReflectionClass($validatorClass)->getFileName()));

                    $realDefinition = $container->get($attribute['definition_id'] ?? throw new \LogicException('The "definition_id" attribute is required.'));
                    (new $validatorClass())->validate($realDefinition, $attribute['name'] ?? throw new \LogicException('The "name" attribute is required.'));
                }
            }
        }
    }
}
