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
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tells which method should be turned into a Closure based on the name of the parameter it's attached to.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AutowireMethodOf extends AutowireCallable
{
    /**
     * @param string            $service The service containing the method to autowire
     * @param bool|class-string $lazy    Whether to use lazy-loading for this argument
     */
    public function __construct(string $service, bool|string $lazy = false)
    {
        parent::__construct([new Reference($service)], lazy: $lazy);
    }

    public function buildDefinition(mixed $value, ?string $type, \ReflectionParameter $parameter): Definition
    {
        $value[1] = $parameter->name;

        return parent::buildDefinition($value, $type, $parameter);
    }
}
