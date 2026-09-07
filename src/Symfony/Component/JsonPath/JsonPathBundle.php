<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\JsonPath\Attribute\AsJsonPathFunction;
use Symfony\Component\JsonPath\DependencyInjection\JsonPathPass;

/**
 * Provides the JsonPath crawler services.
 */
#[RequiredBundle(ServicesBundle::class)]
class JsonPathBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new JsonPathPass());
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import('Resources/config/json_path.php');

        $container->registerAttributeForAutoconfiguration(AsJsonPathFunction::class, static function (ChildDefinition $definition, AsJsonPathFunction $attribute, \ReflectionClass $reflector): void {
            if (!$reflector->hasMethod('__invoke')) {
                throw new LogicException(\sprintf('The "%s" attribute can only be applied to invokable classes, "%s" is not invokable.', AsJsonPathFunction::class, $reflector->name));
            }

            $definition->addTag('json_path.function', [
                'name' => $attribute->name,
                'return_type' => $attribute->returnType->value,
                'arity' => $reflector->getMethod('__invoke')->getNumberOfRequiredParameters(),
            ]);
        });
    }
}
