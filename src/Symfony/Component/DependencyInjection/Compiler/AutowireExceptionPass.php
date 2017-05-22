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
    private $inlineServicePass;

    public function __construct(AutowirePass $autowirePass, InlineServiceDefinitionsPass $inlineServicePass)
    {
        $this->autowirePass = $autowirePass;
        $this->inlineServicePass = $inlineServicePass;
    }

    public function process(ContainerBuilder $container)
    {
        // the pass should only be run once
        if (null === $this->autowirePass || null === $this->inlineServicePass) {
            return;
        }

        $inlinedIds = $this->inlineServicePass->getInlinedServiceIds();
        $exceptions = $this->autowirePass->getAutowiringExceptions();

        // free up references
        $this->autowirePass = null;
        $this->inlineServicePass = null;

        foreach ($exceptions as $exception) {
            if ($container->hasDefinition($exception->getServiceId()) || in_array($exception->getServiceId(), $inlinedIds)) {
                throw $exception;
            }
        }
    }
}
