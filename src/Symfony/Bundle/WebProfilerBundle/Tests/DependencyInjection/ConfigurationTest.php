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
        $config = $processor->processConfiguration($configuration, array($options));

        $this->assertEquals($results, $config);
    }

    /**
     * @dataProvider getPositionConfig
     * @group legacy
     * @expectedDeprecation The "web_profiler.position" configuration key has been deprecated in Symfony 3.4 and it will be removed in 4.0.
     */
    public function testPositionConfig($options, $results)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, array($options));

        $this->assertEquals($results, $config);
    }

    public function getDebugModes()
    {
        return array(
            array(array(), array('intercept_redirects' => false, 'toolbar' => false, 'position' => 'bottom', 'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt')),
            array(array('intercept_redirects' => true), array('intercept_redirects' => true, 'toolbar' => false, 'position' => 'bottom', 'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt')),
            array(array('intercept_redirects' => false), array('intercept_redirects' => false, 'toolbar' => false, 'position' => 'bottom', 'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt')),
            array(array('toolbar' => true), array('intercept_redirects' => false, 'toolbar' => true, 'position' => 'bottom', 'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt')),
            array(array('excluded_ajax_paths' => 'test'), array('intercept_redirects' => false, 'toolbar' => false, 'position' => 'bottom', 'excluded_ajax_paths' => 'test')),
        );
    }

    public function getPositionConfig()
    {
        return array(
            array(array('position' => 'top'), array('intercept_redirects' => false, 'toolbar' => false, 'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt', 'position' => 'top')),
            array(array('position' => 'bottom'), array('intercept_redirects' => false, 'toolbar' => false, 'excluded_ajax_paths' => '^/((index|app(_[\w]+)?)\.php/)?_wdt', 'position' => 'bottom')),
        );
    }
}
