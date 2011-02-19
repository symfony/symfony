<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Tests\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\Configuration;

use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider optionProvider
     * @param array $configs The source array of configuration arrays
     * @param array $correctValues A key-value pair of end values to check
     */
    public function testMergeOptions(array $configs, array $correctValues)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $options = $processor->process($configuration->getConfigTree(), $configs);

        foreach ($correctValues as $key => $correctVal)
        {
            $this->assertEquals($correctVal, $options[$key]);
        }
    }

    public function optionProvider()
    {
        $cases = array();

        // single config, testing normal option setting
        $cases[] = array(
            array(
                array('default_document_manager' => 'foo'),
            ),
            array('default_document_manager' => 'foo')
        );

        // single config, testing normal option setting with dashes
        $cases[] = array(
            array(
                array('default-document-manager' => 'bar'),
            ),
            array('default_document_manager' => 'bar')
        );

        // testing the normal override merging - the later config array wins
        $cases[] = array(
            array(
                array('default_document_manager' => 'foo'),
                array('default_document_manager' => 'baz'),
            ),
            array('default_document_manager' => 'baz')
        );

        // the "options" array is totally replaced
        $cases[] = array(
            array(
                array('options' => array('timeout' => 2000)),
                array('options' => array('username' => 'foo')),
            ),
            array('options' => array('username' => 'foo')),
        );

        // mappings are merged non-recursively.
        $cases[] = array(
            array(
                array('mappings' => array('foomap' => array('type' => 'val1'), 'barmap' => array('dir' => 'val2'))),
                array('mappings' => array('barmap' => array('prefix' => 'val3'))),
            ),
            array('mappings' => array('foomap' => array('type' => 'val1'), 'barmap' => array('prefix' => 'val3'))),
        );

        // connections are merged non-recursively.
        $cases[] = array(
            array(
                array('connections' => array('foocon' => array('server' => 'val1'), 'barcon' => array('options' => array('username' => 'val2')))),
                array('connections' => array('barcon' => array('server' => 'val3'))),
            ),
            array('connections' => array(
                'foocon' => array('server' => 'val1', 'options' => array()),
                'barcon' => array('server' => 'val3', 'options' => array())
            )),
        );

        // managers are merged non-recursively.
        $cases[] = array(
            array(
                array('document_managers' => array('foodm' => array('database' => 'val1'), 'bardm' => array('default_database' => 'val2'))),
                array('document_managers' => array('bardm' => array('database' => 'val3'))),
            ),
            array('document_managers' => array(
                'foodm' => array('database' => 'val1', 'mappings' => array()),
                'bardm' => array('database' => 'val3', 'mappings' => array()),
            )),
        );

        return $cases;
    }

    /**
     * @dataProvider getNormalizationTests
     */
    public function testNormalizeOptions(array $config, $targetKey, array $normalized)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $options = $processor->process($configuration->getConfigTree(), array($config));
        $this->assertSame($normalized, $options[$targetKey]);
    }

    public function getNormalizationTests()
    {
        return array(
            // connection versus connections (id is the identifier)
            array(
                array('connection' => array(
                    array('server' => 'mongodb://abc', 'id' => 'foo'),
                    array('server' => 'mongodb://def', 'id' => 'bar'),
                )),
                'connections',
                array(
                    'foo' => array('server' => 'mongodb://abc', 'options' => array()),
                    'bar' => array('server' => 'mongodb://def', 'options' => array()),
                ),
            ),
            // document_manager versus document_managers (id is the identifier)
            array(
                array('document_manager' => array(
                    array('connection' => 'conn1', 'id' => 'foo'),
                    array('connection' => 'conn2', 'id' => 'bar'),
                )),
                'document_managers',
                array(
                    'foo' => array('connection' => 'conn1', 'mappings' => array()),
                    'bar' => array('connection' => 'conn2', 'mappings' => array()),
                ),
            ),
            // mapping versus mappings (name is the identifier)
            array(
                array('mapping' => array(
                    array('type' => 'yml', 'name' => 'foo'),
                    array('type' => 'xml', 'name' => 'bar'),
                )),
                'mappings',
                array(
                    'foo' => array('type' => 'yml'),
                    'bar' => array('type' => 'xml'),
                ),
            ),
            // mapping configuration that's beneath a specific document manager
            array(
                array('document_manager' => array(
                    array('id' => 'foo', 'connection' => 'conn1', 'mapping' => array(
                        'type' => 'xml', 'name' => 'foo-mapping'
                    )),
                )),
                'document_managers',
                array(
                    'foo' => array('connection' => 'conn1', 'mappings' => array(
                        'foo-mapping' => array('type' => 'xml'),
                    )),
                ),
            ),
        );
    }
}