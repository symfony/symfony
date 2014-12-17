<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle;

use Symfony\Bundle\DebugBundle\DependencyInjection\Compiler\DumpDataCollectorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\VarDumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugBundle extends Bundle
{
    public function boot()
    {
        if ($this->container->getParameter('kernel.debug')) {
            $container = $this->container;

            // This code is here to lazy load the dump stack. This default
            // configuration for CLI mode is overridden in HTTP mode on
            // 'kernel.request' event
            VarDumper::setHandler(function ($var) use ($container) {
                $dumper = new CliDumper();
                $cloner = $container->get('var_dumper.cloner');
                $handler = function ($var) use ($dumper, $cloner) {
                    $dumper->dump($cloner->cloneVar($var));
                };
                VarDumper::setHandler($handler);
                $handler($var);
            });
        }
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new DumpDataCollectorPass());
    }
}
