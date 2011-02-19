<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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
        $loader = new SwiftmailerExtension();

        $loader->load(array(array()), $container);
        $this->assertEquals('Swift_Mailer', $container->getParameter('swiftmailer.class'), '->mailerLoad() loads the swiftmailer.xml file if not already loaded');

        $loader->load(array(array('transport' => 'sendmail')), $container);
        $this->assertEquals('sendmail', $container->getParameter('swiftmailer.transport.name'), '->mailerLoad() overrides existing configuration options');
        $this->assertEquals('swiftmailer.transport.sendmail', (string) $container->getAlias('swiftmailer.transport'));

        $loader->load(array(array()), $container);
        $this->assertEquals('smtp', $container->getParameter('swiftmailer.transport.name'), '->mailerLoad() provides default values for configuration options');
        $this->assertEquals('swiftmailer.transport.smtp', (string) $container->getAlias('swiftmailer.transport'));
    }

    public function testSpool()
    {
        $container = new ContainerBuilder();
        $loader = new SwiftmailerExtension();

        $loader->load(array(array('spool' => array())), $container);
        $this->assertEquals('swiftmailer.transport.spool', (string) $container->getAlias('swiftmailer.transport'));
        $this->assertEquals('swiftmailer.transport.smtp', (string) $container->getAlias('swiftmailer.transport.real'));
    }

}
