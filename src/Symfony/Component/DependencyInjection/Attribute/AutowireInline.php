<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Allows inline service definition for an argument.
 *
 * Using this attribute on a class autowires a new instance
 * which is not shared between different services.
 *
 * $class a FQCN, or an array to define a factory.
 * Use the "@" prefix to reference a service.
 *
 * @author Ismail Özgün Turan <oezguen.turan@dadadev.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AutowireInline extends Autowire
{
    public function __construct(string|array|null $class = null, array $arguments = [], array $calls = [], array $properties = [], ?string $parent = null, bool|string $lazy = false)
    {
        if (null === $class && null === $parent) {
            throw new LogicException('#[AutowireInline] attribute should declare either $class or $parent.');
        }

        parent::__construct([
            \is_array($class) ? 'factory' : 'class' => $class,
            'arguments' => $arguments,
            'calls' => $calls,
            'properties' => $properties,
            'parent' => $parent,
        ], lazy: $lazy);
    }

    public function buildDefinition(mixed $value, ?string $type, \ReflectionParameter $parameter): Definition
    {
        static $parseDefinition;
        static $yamlLoader;

        $parseDefinition ??= new \ReflectionMethod(YamlFileLoader::class, 'parseDefinition');
        $yamlLoader ??= $parseDefinition->getDeclaringClass()->newInstanceWithoutConstructor();

        if (isset($value['factory'])) {
            $value['class'] = $type;
            $value['factory'][0] ??= $type;
            $value['factory'][1] ??= '__invoke';
        }
        $class = $parameter->getDeclaringClass();

        return $parseDefinition->invoke($yamlLoader, $class->name, $value, $class->getFileName(), ['autowire' => true], true);
    }
}
