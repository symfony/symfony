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
    public function testConfigTree($options, $results)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, [$options]);

        $this->assertEquals($results, $config);
    }

    public function getDebugModes()
    {
        return [
            [[], ['intercept_redirects' => false, 'toolbar' => false, 'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt']],
            [['intercept_redirects' => true], ['intercept_redirects' => true, 'toolbar' => false, 'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt']],
            [['intercept_redirects' => false], ['intercept_redirects' => false, 'toolbar' => false, 'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt']],
            [['toolbar' => true], ['intercept_redirects' => false, 'toolbar' => true, 'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt']],
            [['excluded_ajax_paths' => 'test'], ['intercept_redirects' => false, 'toolbar' => false, 'excluded_ajax_paths' => 'test']],
        ];
    }
}
