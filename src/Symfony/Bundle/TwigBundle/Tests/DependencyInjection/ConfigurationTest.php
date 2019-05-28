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
            'form_themes' => ['form_div_layout.html.twig'],
        ];

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [$input]);

        $this->assertEquals(['form_div_layout.html.twig'], $config['form_themes']);
    }

    public function testGlobalsAreNotNormalized()
    {
        $input = [
            'globals' => ['some-global' => true],
        ];

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [$input]);

        $this->assertSame(['some-global' => ['value' => true]], $config['globals']);
    }

    public function testArrayKeysInGlobalsAreNotNormalized()
    {
        $input = [
            'globals' => ['global' => ['some-key' => 'some-value']],
        ];

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [$input]);

        $this->assertSame(['global' => ['value' => ['some-key' => 'some-value']]], $config['globals']);
    }
}
