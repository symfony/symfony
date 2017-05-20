<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Throws autowire exceptions from AutowirePass for definitions that still exist.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class AutowireExceptionPass implements CompilerPassInterface
{
    private $autowirePass;

    public function __construct(AutowirePass $autowirePass)
    {
        $this->autowirePass = $autowirePass;
    }

    public function process(ContainerBuilder $container)
    {
        foreach ($this->autowirePass->getAutowiringExceptions() as $exception) {
            if ($container->hasDefinition($exception->getServiceId())) {
                throw $exception;
            }
        }
    }
}
