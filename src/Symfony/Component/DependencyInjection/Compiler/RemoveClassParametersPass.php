<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Definition;

use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes foo.class parameters and replace their values inline
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class RemoveClassParametersPass implements CompilerPassInterface
{
    private $container;
    private $aggressive;

    /**
     * @param Boolean $aggressive If true, all *.class parameters are removed,
     *                            otherwise only those that are used in the DIC are.
     */
    public function __construct($aggressive = true)
    {
        $this->aggressive = $aggressive;
    }

    public function process(ContainerBuilder $container)
    {
        $this->container = $container;

        $classes = array();

        if (false === $this->aggressive) {
            foreach ($container->getDefinitions() as $definition) {
                $classes[$definition->getClass()] = true;
            }
        }

        $classParams = array();
        foreach ($container->getParameterBag()->all() as $param => $value) {
            if ('.class' === substr($param, -6) && (true === $this->aggressive || isset($classes[$value]))) {
                $classParams[] = $param;
            }
        }
        $container->getParameterBag()->remove($classParams);
    }
}
