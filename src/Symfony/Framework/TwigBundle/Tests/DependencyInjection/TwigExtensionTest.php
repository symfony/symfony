<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Framework\TwigBundle\Tests\DependencyInjection;

use Symfony\Framework\TwigBundle\Tests\TestCase;
use Symfony\Framework\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Components\DependencyInjection\BuilderConfiguration;

class TwigExtensionTest extends TestCase
{
    public function testConfigLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = new TwigExtension();

        $configuration = $loader->configLoad(array(), $configuration);
        $this->assertEquals('Symfony\\Framework\\TwigBundle\\Environment', $configuration->getParameter('twig.class'), '->configLoad() loads the twig.xml file if not already loaded');

        $configuration = $loader->configLoad(array('charset' => 'ISO-8859-1'), $configuration);
        $options = $configuration->getParameter('twig.options');
        $this->assertEquals('ISO-8859-1', $options['charset'], '->configLoad() overrides existing configuration options');
        $this->assertEquals('%kernel.debug%', $options['debug'], '->configLoad() merges the new values with the old ones');
    }
}
