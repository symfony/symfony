<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Framework\SwiftmailerBundle\Tests\DependencyInjection;

use Symfony\Framework\SwiftmailerBundle\Tests\TestCase;
use Symfony\Framework\SwiftmailerBundle\DependencyInjection\SwiftmailerExtension;
use Symfony\Components\DependencyInjection\BuilderConfiguration;

class SwiftmailerExtensionTest extends TestCase
{
    public function testMailerLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = new SwiftmailerExtension();

        $configuration = $loader->mailerLoad(array(), $configuration);
        $this->assertEquals('Swift_Mailer', $configuration->getParameter('swiftmailer.class'), '->mailerLoad() loads the swiftmailer.xml file if not already loaded');

        $configuration = $loader->mailerLoad(array('transport' => 'sendmail'), $configuration);
        $this->assertEquals('sendmail', $configuration->getParameter('swiftmailer.transport.name'), '->mailerLoad() overrides existing configuration options');
        $configuration = $loader->mailerLoad(array(), $configuration);
        $this->assertEquals('sendmail', $configuration->getParameter('swiftmailer.transport.name'), '->mailerLoad() overrides existing configuration options');
    }
}
