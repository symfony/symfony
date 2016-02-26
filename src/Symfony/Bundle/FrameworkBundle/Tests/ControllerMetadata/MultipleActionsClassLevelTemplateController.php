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
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @Template("FooBundle:Invokable:predefined.html.twig")
 */
class MultipleActionsClassLevelTemplateController extends Controller
{
    /**
     * @Route("/multi/one-template/1/")
     */
    public function firstAction()
    {
        return array(
            'foo' => 'bar',
        );
    }

    /**
     * @Route("/multi/one-template/2/")
     * @Route("/multi/one-template/3/")
     */
    public function secondAction()
    {
        return array(
            'foo' => 'bar',
        );
    }

    /**
     * @Route("/multi/one-template/4/")
     * @Template("FooBundle::overwritten.html.twig")
     */
    public function overwriteAction()
    {
        return array(
            'foo' => 'foo bar baz',
        );
    }
}
