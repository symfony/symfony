<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\Tests\DependencyInjection;

use Symfony\Bundle\SwiftmailerBundle\Tests\TestCase;
use Symfony\Bundle\SwiftmailerBundle\DependencyInjection\SwiftmailerExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SwiftmailerExtensionTest extends TestCase
{
    public function testConfigLoad()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $loader = new SwiftmailerExtension();

        $loader->load(array(array()), $container);
        $this->assertEquals('Swift_Mailer', $container->getParameter('swiftmailer.class'), '->load() loads the swiftmailer.xml file if not already loaded');

        $loader->load(array(array('transport' => 'sendmail')), $container);
        $this->assertEquals('swiftmailer.transport.sendmail', (string) $container->getAlias('swiftmailer.transport'));

        $loader->load(array(array()), $container);
        $this->assertEquals('swiftmailer.transport.smtp', (string) $container->getAlias('swiftmailer.transport'));
    }

    public function testNullTransport()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $loader = new SwiftmailerExtension();

        $loader->load(array(array('transport' => null)), $container);
        $this->assertEquals('swiftmailer.transport.null', (string) $container->getAlias('swiftmailer.transport'));
    }

    public function testSpool()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $loader = new SwiftmailerExtension();

        $loader->load(array(array('spool' => array())), $container);
        $this->assertEquals('swiftmailer.transport.spool', (string) $container->getAlias('swiftmailer.transport'));
        $this->assertEquals('swiftmailer.transport.smtp', (string) $container->getAlias('swiftmailer.transport.real'));
    }

}
