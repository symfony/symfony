<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\Validator\DependencyInjection\AddValidatorInitializersPass;

class AddValidatorInitializersPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container
            ->register('initializer1')
            ->addTag('validator.initializer')
        ;
        $container
            ->register('initializer2')
            ->addTag('validator.initializer')
        ;
        $container
            ->register('validator.builder')
            ->addArgument(array())
        ;

        (new AddValidatorInitializersPass())->process($container);

        $this->assertEquals(
            array(array('addObjectInitializers', array(array(new Reference('initializer1'), new Reference('initializer2'))))),
            $container->getDefinition('validator.builder')->getMethodCalls()
        );
    }
}
