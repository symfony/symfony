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
        $input = [
            'strict_variables' => false, // to be removed in 5.0 relying on default
            'exception_controller' => null, // to be removed in 5.0
            'form_themes' => ['form_div_layout.html.twig'],
        ];

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [$input]);

        $this->assertEquals(['form_div_layout.html.twig'], $config['form_themes']);
    }

    /**
     * @group legacy
     * @expectedDeprecation Relying on the default value ("false") of the "twig.strict_variables" configuration option is deprecated since Symfony 4.1. You should use "%kernel.debug%" explicitly instead, which will be the new default in 5.0.
     */
    public function testGetStrictVariablesDefaultFalse()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [[]]);

        $this->assertFalse($config['strict_variables']);
    }

    /**
     * @group legacy
     * @expectedDeprecation The "twig.exception_controller" configuration key has been deprecated in Symfony 4.4, set it to "null" and use "framework.error_controller" configuration key instead.
     */
    public function testGetExceptionControllerDefault()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [['exception_controller' => 'exception_controller']]);

        $this->assertSame('exception_controller', $config['exception_controller']);
    }

    public function testGlobalsAreNotNormalized()
    {
        $input = [
            'strict_variables' => false, // to be removed in 5.0 relying on default
            'exception_controller' => null, // to be removed in 5.0 relying on default
            'globals' => ['some-global' => true],
        ];

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [$input]);

        $this->assertSame(['some-global' => ['value' => true]], $config['globals']);
    }

    public function testArrayKeysInGlobalsAreNotNormalized()
    {
        $input = [
            'strict_variables' => false, // to be removed in 5.0 relying on default
            'exception_controller' => null, // to be removed in 5.0 relying on default
            'globals' => ['global' => ['some-key' => 'some-value']],
        ];

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [$input]);

        $this->assertSame(['global' => ['value' => ['some-key' => 'some-value']]], $config['globals']);
    }
}
