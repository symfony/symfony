<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Bundle\TwigBundle\TokenParser\ExtendsByConfigTokenParser;

/**
 *
 * @author Cedric LOMBARDOT <cedric.lombardot@gmail.com>
 */
class ExtendsByConfigExtension extends \Twig_Extension
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getTokenParsers()
    {
        return array(
            //{% extends_by_config 'container.parameter_name' %}
            new ExtendsByConfigTokenParser($this->container),
        );
    }

    public function getName()
    {
        return 'extends_by_config';
    }
}
