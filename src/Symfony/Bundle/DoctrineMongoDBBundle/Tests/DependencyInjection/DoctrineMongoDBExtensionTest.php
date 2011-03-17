<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Tests\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\DoctrineMongoDBExtension;

use Symfony\Component\Config\Definition\Processor;

class DoctrineMongoDBExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider parameterProvider
     */
    public function testParameterOverride($option, $parameter, $value)
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $loader = new DoctrineMongoDBExtension();
        $loader->load(array(array($option => $value)), $container);

        $this->assertEquals($value, $container->getParameter('doctrine.odm.mongodb.'.$parameter));
    }

    public function parameterProvider()
    {
        return array(
            array('proxy_namespace', 'proxy_namespace', 'foo'),
            array('proxy-namespace', 'proxy_namespace', 'bar'),
        );
    }
}