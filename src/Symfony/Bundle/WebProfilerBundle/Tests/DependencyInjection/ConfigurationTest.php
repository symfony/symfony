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

    public static function getDebugModes()
    {
        return [
            [
                'options' => [],
                'expectedResult' => [
                    'intercept_redirects' => false,
                    'toolbar' => false,
                    'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt',
                ],
            ],
            [
                'options' => ['toolbar' => true],
                'expectedResult' => [
                    'intercept_redirects' => false,
                    'toolbar' => true,
                    'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt',
                ],
            ],
            [
                'options' => ['excluded_ajax_paths' => 'test'],
                'expectedResult' => [
                    'intercept_redirects' => false,
                    'toolbar' => false,
                    'excluded_ajax_paths' => 'test',
                ],
            ],
        ];
    }

    /**
     * @dataProvider getInterceptRedirectsConfiguration
     */
    public function testConfigTreeUsingInterceptRedirects(bool $interceptRedirects, array $expectedResult)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, [['intercept_redirects' => $interceptRedirects]]);

        $this->assertEquals($expectedResult, $config);
    }

    public static function getInterceptRedirectsConfiguration()
    {
        return [
            [
                'interceptRedirects' => true,
                'expectedResult' => [
                    'intercept_redirects' => true,
                    'toolbar' => false,
                    'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt',
                ],
            ],
            [
                'interceptRedirects' => false,
                'expectedResult' => [
                    'intercept_redirects' => false,
                    'toolbar' => false,
                    'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt',
                ],
            ],
        ];
    }
}
