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

class InvokableContainerController extends Controller
{
    /**
     * @Route("/invokable/variable/container/{variable}/")
     * @Template()
     */
    public function variableAction($variable)
    {
    }

    /**
     * @Route("/invokable/another-variable/container/{variable}/")
     * @Template("FooBundle:InvokableContainer:variable.html.twig")
     */
    public function anotherVariableAction($variable)
    {
        return array(
            'variable' => $variable,
        );
    }

    /**
     * @Route("/invokable/variable/container/{variable}/{another_variable}/")
     * @Template("FooBundle:InvokableContainer:another_variable.html.twig")
     */
    public function doubleVariableAction($variable, $another_variable)
    {
        return array(
            'variable' => $variable,
            'another_variable' => $another_variable,
        );
    }

    /**
     * @Route("/invokable/predefined/container/")
     * @Template("FooBundle:Invokable:predefined.html.twig")
     */
    public function __invoke()
    {
        return array(
            'foo' => 'bar',
        );
    }
}
