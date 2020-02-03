<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Add support of url property for routing generator
 *
 * @author Danil Pyatnitsev <danil@pyatnitsev.ru>
 */
class RequestContextPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasParameter('router.request_context.url')) {
            return;
        }

        $url = $container->getParameter('router.request_context.url');
        $urlComponents = parse_url($url);

        if (isset($urlComponents['scheme'])) {
            $container->setParameter('router.request_context.scheme', $urlComponents['scheme']);
        }
        if (isset($urlComponents['host'])) {
            $container->setParameter('router.request_context.host', $urlComponents['host']);
        }
        if (isset($urlComponents['port'])) {
            $name = (isset($urlComponents['scheme']) && 'https' === $urlComponents['scheme']) ? 'https' : 'http';
            $container->setParameter("request_listener.{$name}_port", $urlComponents['port']);
        }
        if (isset($urlComponents['path'])) {
            $container->setParameter("router.request_context.base_url", $urlComponents['path']);
        }
    }
}