<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    /**
     * @dataProvider getDebugModes
     */
    public function testConfigTree(array $options, array $expectedResult)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, [$options]);

        $this->assertEquals($expectedResult, $config);
    }

    public function getDebugModes()
    {
        return [
            [
                'options' => [],
                'expectedResult' => [
                    'toolbar' => false,
                    'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt',
                ],
            ],
            [
                'options' => ['toolbar' => true],
                'expectedResult' => [
                    'toolbar' => true,
                    'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt',
                ],
            ],
            [
                'options' => ['excluded_ajax_paths' => 'test'],
                'expectedResult' => [
                    'toolbar' => false,
                    'excluded_ajax_paths' => 'test',
                ],
            ],
        ];
    }
}
