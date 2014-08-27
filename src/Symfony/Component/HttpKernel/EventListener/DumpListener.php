<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Configures dump() handler.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DumpListener implements EventSubscriberInterface
{
    private $container;
    private $dumper;

    /**
     * @param ContainerInterface $container Service container, for lazy loading.
     * @param string             $dumper    var_dumper dumper service to use.
     */
    public function __construct(ContainerInterface $container, $dumper)
    {
        $this->container = $container;
        $this->dumper = $dumper;
    }

    public function configure()
    {
        if ($this->container) {
            $container = $this->container;
            $dumper = $this->dumper;
            $this->container = null;

            VarDumper::setHandler(function ($var) use ($container, $dumper) {
                $dumper = $container->get($dumper);
                $cloner = $container->get('var_dumper.cloner');
                $handler = function ($var) use ($dumper, $cloner) {$dumper->dump($cloner->cloneVar($var));};
                VarDumper::setHandler($handler);
                $handler($var);
            });
        }
    }

    public static function getSubscribedEvents()
    {
        // Register early to have a working dump() as early as possible
        return array(KernelEvents::REQUEST => array('configure', 1024));
    }
}
