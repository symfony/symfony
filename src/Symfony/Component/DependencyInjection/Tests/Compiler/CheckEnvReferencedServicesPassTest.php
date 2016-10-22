<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CheckEnvReferencedServicesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\GetEnvInterface;

class CheckEnvReferencedServicePassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->getParameterBag()->get('env(foo@a)');
        $container->register('a', GetEnvService::class);

        $pass = new CheckEnvReferencedServicesPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function testProcessThrowsExceptionOnInvalidReference()
    {
        $container = new ContainerBuilder();
        $container->getParameterBag()->get('env(foo@a)');

        $pass = new CheckEnvReferencedServicesPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testProcessThrowsExceptionOnInvalidReferenceFromInlinedDefinition()
    {
        $container = new ContainerBuilder();
        $container->getParameterBag()->get('env(foo@a)');
        $container->register('a', \stdClass::class);

        $pass = new CheckEnvReferencedServicesPass();
        $pass->process($container);
    }
}

class GetEnvService implements GetEnvInterface
{
    public function getEnv($name)
    {
        return $name.$name;
    }
}
