<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\DependencyInjection\ReverseMappingPass;

/**
 * Provides the object mapper services.
 */
#[RequiredBundle(ServicesBundle::class)]
class ObjectMapperBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ReverseMappingPass());
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import('Resources/config/object_mapper.php');

        $container->registerForAutoconfiguration(TransformCallableInterface::class)
            ->addTag('object_mapper.transform_callable');
        $container->registerForAutoconfiguration(ConditionCallableInterface::class)
            ->addTag('object_mapper.condition_callable');
        $container->registerAttributeForAutoconfiguration(Map::class, static function (ChildDefinition $definition, Map $attribute, \ReflectionClass $reflector): void {
            $definition->addResourceTag('object_mapper.map', [
                'source' => $attribute->source ?? $reflector->name,
                'target' => $attribute->target ?? $reflector->name,
            ]);
        });
    }
}
