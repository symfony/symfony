<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Loader;

use Symfony\Component\Translation\Tests\fixtures\RemoteLoader;

class RemoteLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testEmptyLoader()
    {
        $loader = new RemoteLoader(array());

        $this->assertEquals(array(), $loader->getRemoteResources());
        $this->assertEquals(array(), $loader->getLocalesForResource('foo'));
        $this->assertEquals(array(), $loader->getDomainsForLocale('foo', 'bar'));
        $this->assertEquals(array(), $loader->load('foo', 'bar', 'baz'));
    }

    public function testEmptyResources()
    {
        $loader = new RemoteLoader(array(
            'foo' => array(),
        ));

        $this->assertEquals(array('foo'), $loader->getRemoteResources());
        $this->assertEquals(array(), $loader->getLocalesForResource('foo'));
        $this->assertEquals(array(), $loader->getDomainsForLocale('foo', 'bar'));
        $this->assertEquals(array(), $loader->load('foo', 'bar', 'baz'));
    }

    public function testEmptyLocales()
    {
        $loader = new RemoteLoader(array(
            'foo' => array(
                'pl_PL' => array(),
            ),
        ));

        $this->assertEquals(array('foo'), $loader->getRemoteResources());
        $this->assertEquals(array('pl_PL'), $loader->getLocalesForResource('foo'));
        $this->assertEquals(array(), $loader->getDomainsForLocale('foo', 'bar'));
        $this->assertEquals(array(), $loader->load('foo', 'bar', 'baz'));
    }

    public function testEmptyDomains()
    {
        $loader = new RemoteLoader(array(
            'foo' => array(
                'pl_PL' => array(
                    'messages' => array(),
                ),
            ),
        ));

        $this->assertEquals(array('foo'), $loader->getRemoteResources());
        $this->assertEquals(array('pl_PL'), $loader->getLocalesForResource('foo'));
        $this->assertEquals(array('messages'), $loader->getDomainsForLocale('foo', 'pl_PL'));
        $this->assertEquals(array(), $loader->load('foo', 'pl_PL', 'messages'));
    }

    public function testLoad()
    {
        $loader = new RemoteLoader(array(
            'foo' => array(
                'pl_PL' => array(
                    'messages' => array(
                        'key1' => 'value1',
                        'key2' => 'value2',
                    ),
                ),
            ),
        ));

        $this->assertEquals(array('foo'), $loader->getRemoteResources());
        $this->assertEquals(array('pl_PL'), $loader->getLocalesForResource('foo'));
        $this->assertEquals(array('messages'), $loader->getDomainsForLocale('foo', 'pl_PL'));
        $this->assertEquals(array('key1' => 'value1', 'key2' => 'value2'), $loader->load('foo', 'pl_PL', 'messages'));
    }

    public function testLoadMultipleLocales()
    {
        $loader = new RemoteLoader(array(
            'foo' => array(
                'pl_PL' => array(
                    'messages' => array(
                        'key1' => 'value1',
                        'key2' => 'value2',
                    ),
                ),
                'en_US' => array(
                    'messages' => array(
                        'key3' => 'value3',
                        'key4' => 'value4',
                    ),
                ),
            ),
        ));

        $this->assertEquals(array('foo'), $loader->getRemoteResources());
        $this->assertEquals(array('pl_PL', 'en_US'), $loader->getLocalesForResource('foo'));
        $this->assertEquals(array('messages'), $loader->getDomainsForLocale('foo', 'pl_PL'));
        $this->assertEquals(array('messages'), $loader->getDomainsForLocale('foo', 'en_US'));
        $this->assertEquals(array('key1' => 'value1', 'key2' => 'value2'), $loader->load('foo', 'pl_PL', 'messages'));
        $this->assertEquals(array('key3' => 'value3', 'key4' => 'value4'), $loader->load('foo', 'en_US', 'messages'));
    }

    public function testLoadMultipleResources()
    {
        $loader = new RemoteLoader(array(
            'foo' => array(
                'pl_PL' => array(
                    'messages' => array(
                        'key1' => 'value1',
                        'key2' => 'value2',
                    ),
                ),
            ),
            'bar' => array(
                'en_US' => array(
                    'messages' => array(
                        'key3' => 'value3',
                        'key4' => 'value4',
                    ),
                ),
            ),
        ));

        $this->assertEquals(array('foo', 'bar'), $loader->getRemoteResources());
        $this->assertEquals(array('pl_PL'), $loader->getLocalesForResource('foo'));
        $this->assertEquals(array('en_US'), $loader->getLocalesForResource('bar'));
        $this->assertEquals(array('messages'), $loader->getDomainsForLocale('foo', 'pl_PL'));
        $this->assertEquals(array('messages'), $loader->getDomainsForLocale('bar', 'en_US'));
        $this->assertEquals(array('key1' => 'value1', 'key2' => 'value2'), $loader->load('foo', 'pl_PL', 'messages'));
        $this->assertEquals(array('key3' => 'value3', 'key4' => 'value4'), $loader->load('bar', 'en_US', 'messages'));
    }

    public function testLoadMultipleDomains()
    {
        $loader = new RemoteLoader(array(
            'foo' => array(
                'pl_PL' => array(
                    'messages' => array(
                        'key1' => 'value1',
                        'key2' => 'value2',
                    ),
                    'site' => array(
                        'key3' => 'value3',
                        'key4' => 'value4',
                    ),
                ),
            ),
        ));

        $this->assertEquals(array('foo'), $loader->getRemoteResources());
        $this->assertEquals(array('pl_PL'), $loader->getLocalesForResource('foo'));
        $this->assertEquals(array('messages', 'site'), $loader->getDomainsForLocale('foo', 'pl_PL'));
        $this->assertEquals(array('key1' => 'value1', 'key2' => 'value2'), $loader->load('foo', 'pl_PL', 'messages'));
        $this->assertEquals(array('key3' => 'value3', 'key4' => 'value4'), $loader->load('foo', 'pl_PL', 'site'));
    }
}
