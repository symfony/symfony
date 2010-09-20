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

        $loader->configLoad(array(), $container);
        $this->assertEquals('Swift_Mailer', $container->getParameter('swiftmailer.class'), '->mailerLoad() loads the swiftmailer.xml file if not already loaded');

        $loader->configLoad(array('transport' => 'sendmail'), $container);
        $this->assertEquals('sendmail', $container->getParameter('swiftmailer.transport.name'), '->mailerLoad() overrides existing configuration options');
        $loader->configLoad(array(), $container);
        $this->assertEquals('sendmail', $container->getParameter('swiftmailer.transport.name'), '->mailerLoad() overrides existing configuration options');
    }
}
