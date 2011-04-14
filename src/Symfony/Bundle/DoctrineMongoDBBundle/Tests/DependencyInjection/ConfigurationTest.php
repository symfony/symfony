<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Tests\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\Configuration;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $processor = new Processor();
        $configuration = new Configuration(false);
        $options = $processor->processConfiguration($configuration, array());

        $defaults = array(
            'auto_generate_hydrator_classes' => false,
            'auto_generate_proxy_classes'    => false,
            'default_database'               => 'default',
            'document_managers'              => array(),
            'connections'                    => array(),
            'proxy_dir'                      => '%kernel.cache_dir%/doctrine/odm/mongodb/Proxies',
            'proxy_namespace'                => 'Proxies',
            'hydrator_dir'                   => '%kernel.cache_dir%/doctrine/odm/mongodb/Hydrators',
            'hydrator_namespace'             => 'Hydrators',
        );

        foreach ($defaults as $key => $default) {
            $this->assertTrue(array_key_exists($key, $options), sprintf('The default "%s" exists', $key));
            $this->assertEquals($default, $options[$key]);

            unset($options[$key]);
        }

        if (count($options)) {
            $this->fail('Extra defaults were returned: '. print_r($options, true));
        }
    }

    /**
     * Tests a full configuration.
     *
     * @dataProvider fullConfigurationProvider
     */
    public function testFullConfiguration($config)
    {
        $processor = new Processor();
        $configuration = new Configuration(false);
        $options = $processor->processConfiguration($configuration, array($config));

        $expected = array(
            'proxy_dir'                         => '%kernel.cache_dir%/doctrine/odm/mongodb/Proxies',
            'proxy_namespace'                   => 'Test_Proxies',
            'auto_generate_proxy_classes'       => true,
            'hydrator_dir'                      => '%kernel.cache_dir%/doctrine/odm/mongodb/Hydrators',
            'hydrator_namespace'                => 'Test_Hydrators',
            'auto_generate_hydrator_classes'    => true,
            'default_document_manager'          => 'default_dm_name',
            'default_database'                  => 'default_db_name',
            'default_connection'                => 'conn1',
            'connections'   => array(
                'conn1'         => array(
                    'server'    => 'http://server',
                    'options'   => array(
                        'connect'   => true,
                        'persist'   => 'persist_val',
                        'timeout'   => 500,
                        'replicaSet' => true,
                        'username'  => 'username_val',
                        'password'  => 'password_val',
                    ),
                ),
                'conn2'         => array(
                    'server'    => 'http://server2',
                    'options'   => array(),
                ),
            ),
            'document_managers' => array(
                'dm1' => array(
                    'mappings' => array(
                        'FooBundle'     => array(
                            'type' => 'annotations',
                        ),
                    ),
                    'metadata_cache_driver' => array(
                        'type'      => 'memcache',
                        'class'     => 'fooClass',
                        'host'      => 'host_val',
                        'port'      => 1234,
                        'instance_class' => 'instance_val',
                    ),
                    'logging' => false,
                ),
                'dm2' => array(
                    'connection' => 'dm2_connection',
                    'database' => 'db1',
                    'mappings' => array(
                        'BarBundle' => array(
                            'type'      => 'yml',
                            'dir'       => '%kernel.cache_dir%',
                            'prefix'    => 'prefix_val',
                            'alias'     => 'alias_val',
                            'is_bundle' => false,
                        )
                    ),
                    'metadata_cache_driver' => array(
                        'type' => 'apc',
                    ),
                    'logging' => true,
                )
            )
        );

        $this->assertEquals($expected, $options);
    }

    public function fullConfigurationProvider()
    {
      $yaml = Yaml::load(__DIR__.'/Fixtures/config/yml/full.yml');
      $yaml = $yaml['doctrine_mongo_db'];

       return array(
           array($yaml),
       );
    }

    /**
     * @dataProvider optionProvider
     * @param array $configs The source array of configuration arrays
     * @param array $correctValues A key-value pair of end values to check
     */
    public function testMergeOptions(array $configs, array $correctValues)
    {
        $processor = new Processor();
        $configuration = new Configuration(false);
        $options = $processor->processConfiguration($configuration, $configs);

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
                array('connections' => array('default' => array('options' => array('timeout' => 2000)))),
                array('connections' => array('default' => array('options' => array('username' => 'foo')))),
            ),
            array('connections' => array('default' => array('options' => array('username' => 'foo'), 'server' => null))),
        );

        // mappings are merged non-recursively.
        $cases[] = array(
            array(
                array('document_managers' => array('default' => array('mappings' => array('foomap' => array('type' => 'val1'), 'barmap' => array('dir' => 'val2'))))),
                array('document_managers' => array('default' => array('mappings' => array('barmap' => array('prefix' => 'val3'))))),
            ),
            array('document_managers' => array('default' => array('logging' => false, 'mappings' => array('foomap' => array('type' => 'val1'), 'barmap' => array('prefix' => 'val3'))))),
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
                array('document_managers' => array('foodm' => array('database' => 'val1'), 'bardm' => array('database' => 'val2'))),
                array('document_managers' => array('bardm' => array('database' => 'val3'))),
            ),
            array('document_managers' => array(
                'foodm' => array('database' => 'val1', 'logging' => false, 'mappings' => array()),
                'bardm' => array('database' => 'val3', 'logging' => false, 'mappings' => array()),
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
        $configuration = new Configuration(false);
        $options = $processor->processConfiguration($configuration, array($config));
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
                    'foo' => array('connection' => 'conn1', 'logging' => false, 'mappings' => array()),
                    'bar' => array('connection' => 'conn2', 'logging' => false, 'mappings' => array()),
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
                    ), 'logging' => false),
                ),
            ),
        );
    }
}
