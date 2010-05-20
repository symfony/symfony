<?php

namespace Symfony\Framework\TwigBundle;

use Symfony\Components\DependencyInjection\ContainerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * TwigExtension.
 *
 * @package    Symfony
 * @subpackage Framework_TwigBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Environment extends \Twig_Environment
{
    public function __construct(ContainerInterface $container, \Twig_LoaderInterface $loader = null, $options = array())
    {
        parent::__construct($loader, $options);

        foreach ($container->findAnnotatedServiceIds('twig.extension') as $id => $attributes) {
            $this->addExtension($container->getService($id));
        }
    }
}
