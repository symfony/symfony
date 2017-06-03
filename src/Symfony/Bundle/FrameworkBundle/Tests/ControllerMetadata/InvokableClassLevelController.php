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

/**
 * @Route(service="test.invokable_class_level.predefined")
 * @Template("FooBundle:Invokable:predefined.html.twig")
 */
class InvokableClassLevelController
{
    /**
     * @Route("/invokable/class-level/service/")
     */
    public function __invoke()
    {
        return array(
            'foo' => 'bar',
        );
    }
}
