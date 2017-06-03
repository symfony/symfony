<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\ControllerMetadata;

use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="test.simple.multiple")
 */
class SimpleController
{
    /**
     * @Route("/simple/multiple/", defaults={"a": "a", "b": "b"})
     * @Template()
     */
    public function someAction($a, $b, $c = 'c')
    {
    }

    /**
     * @Route("/simple/multiple/{a}/{b}/")
     * @Template("FooBundle:Simple:some.html.twig")
     */
    public function someMoreAction($a, $b, $c = 'c')
    {
    }

    /**
     * @Route("/simple/multiple-with-vars/", defaults={"a": "a", "b": "b"})
     * @Template(vars={"a", "b"})
     */
    public function anotherAction($a, $b, $c = 'c')
    {
    }

    /**
     * @Route("/no-listener/")
     */
    public function noListenerAction()
    {
        return new Response('<html><body>I did not get rendered via twig</body></html>');
    }

    /**
     * @Route("/streamed/")
     * @Template(isStreamable=true)
     */
    public function streamedAction()
    {
        return array(
            'foo' => 'foo',
            'bar' => 'bar',
        );
    }
}
