<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\TwigBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDoNoDuplicateDefaultFormResources()
    {
        $input = array(
            'strict_variables' => false, // to be removed in 5.0 relying on default
            'form_themes' => array('form_div_layout.html.twig'),
        );

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), array($input));

        $this->assertEquals(array('form_div_layout.html.twig'), $config['form_themes']);
    }

    /**
     * @group legacy
     * @expectedDeprecation Relying on the default value ("false") of the "twig.strict_variables" configuration option is deprecated since Symfony 4.1. You should use "%kernel.debug%" explicitly instead, which will be the new default in 5.0.
     */
    public function testGetStrictVariablesDefaultFalse()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), array(array()));

        $this->assertFalse($config['strict_variables']);
    }

    public function testGlobalsAreNotNormalized()
    {
        $input = array(
            'strict_variables' => false, // to be removed in 5.0 relying on default
            'globals' => array('some-global' => true),
        );

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), array($input));

        $this->assertSame(array('some-global' => array('value' => true)), $config['globals']);
    }

    public function testArrayKeysInGlobalsAreNotNormalized()
    {
        $input = array(
            'strict_variables' => false, // to be removed in 5.0 relying on default
            'globals' => array('global' => array('some-key' => 'some-value')),
        );

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), array($input));

        $this->assertSame(array('global' => array('value' => array('some-key' => 'some-value'))), $config['globals']);
    }
}
