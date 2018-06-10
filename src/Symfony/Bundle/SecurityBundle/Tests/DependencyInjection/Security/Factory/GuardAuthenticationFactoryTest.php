<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Security\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\GuardAuthenticationFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class GuardAuthenticationFactoryTest extends TestCase
{
    /**
     * @dataProvider getValidConfigurationTests
     */
    public function testAddValidConfiguration(array $inputConfig, array $expectedConfig)
    {
        $factory = new GuardAuthenticationFactory();
        $nodeDefinition = new ArrayNodeDefinition('guard');
        $factory->addConfiguration($nodeDefinition);

        $node = $nodeDefinition->getNode();
        $normalizedConfig = $node->normalize($inputConfig);
        $finalizedConfig = $node->finalize($normalizedConfig);

        $this->assertEquals($expectedConfig, $finalizedConfig);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @dataProvider getInvalidConfigurationTests
     */
    public function testAddInvalidConfiguration(array $inputConfig)
    {
        $factory = new GuardAuthenticationFactory();
        $nodeDefinition = new ArrayNodeDefinition('guard');
        $factory->addConfiguration($nodeDefinition);

        $node = $nodeDefinition->getNode();
        $normalizedConfig = $node->normalize($inputConfig);
        // will validate and throw an exception on invalid
        $node->finalize($normalizedConfig);
    }

    public function getValidConfigurationTests()
    {
        $tests = array();

        // completely basic
        $tests[] = array(
            array(
                'authenticators' => array('authenticator1', 'authenticator2'),
                'provider' => 'some_provider',
                'entry_point' => 'the_entry_point',
            ),
            array(
                'authenticators' => array('authenticator1', 'authenticator2'),
                'provider' => 'some_provider',
                'entry_point' => 'the_entry_point',
            ),
        );

        // testing xml config fix: authenticator -> authenticators
        $tests[] = array(
            array(
                'authenticator' => array('authenticator1', 'authenticator2'),
            ),
            array(
                'authenticators' => array('authenticator1', 'authenticator2'),
                'entry_point' => null,
            ),
        );

        return $tests;
    }

    public function getInvalidConfigurationTests()
    {
        $tests = array();

        // testing not empty
        $tests[] = array(
            array('authenticators' => array()),
        );

        return $tests;
    }

    public function testBasicCreate()
    {
        // simple configuration
        $config = array(
            'authenticators' => array('authenticator123'),
            'entry_point' => null,
        );
        list($container, $entryPointId) = $this->executeCreate($config, null);
        $this->assertEquals('authenticator123', $entryPointId);

        $providerDefinition = $container->getDefinition('security.authentication.provider.guard.my_firewall');
        $this->assertEquals(array(
            'index_0' => new IteratorArgument(array(new Reference('authenticator123'))),
            'index_1' => new Reference('my_user_provider'),
            'index_2' => 'my_firewall',
            'index_3' => new Reference('security.user_checker.my_firewall'),
        ), $providerDefinition->getArguments());

        $listenerDefinition = $container->getDefinition('security.authentication.listener.guard.my_firewall');
        $this->assertEquals('my_firewall', $listenerDefinition->getArgument(2));
        $this->assertEquals(array(new Reference('authenticator123')), $listenerDefinition->getArgument(3)->getValues());
    }

    public function testExistingDefaultEntryPointUsed()
    {
        // any existing default entry point is used
        $config = array(
            'authenticators' => array('authenticator123'),
            'entry_point' => null,
        );
        list(, $entryPointId) = $this->executeCreate($config, 'some_default_entry_point');
        $this->assertEquals('some_default_entry_point', $entryPointId);
    }

    /**
     * @expectedException \LogicException
     */
    public function testCannotOverrideDefaultEntryPoint()
    {
        // any existing default entry point is used
        $config = array(
            'authenticators' => array('authenticator123'),
            'entry_point' => 'authenticator123',
        );
        $this->executeCreate($config, 'some_default_entry_point');
    }

    /**
     * @expectedException \LogicException
     */
    public function testMultipleAuthenticatorsRequiresEntryPoint()
    {
        // any existing default entry point is used
        $config = array(
            'authenticators' => array('authenticator123', 'authenticatorABC'),
            'entry_point' => null,
        );
        $this->executeCreate($config, null);
    }

    public function testCreateWithEntryPoint()
    {
        // any existing default entry point is used
        $config = array(
            'authenticators' => array('authenticator123', 'authenticatorABC'),
            'entry_point' => 'authenticatorABC',
        );
        list($container, $entryPointId) = $this->executeCreate($config, null);
        $this->assertEquals('authenticatorABC', $entryPointId);
    }

    private function executeCreate(array $config, $defaultEntryPointId)
    {
        $container = new ContainerBuilder();
        $container->register('security.authentication.provider.guard');
        $container->register('security.authentication.listener.guard');
        $id = 'my_firewall';
        $userProviderId = 'my_user_provider';

        $factory = new GuardAuthenticationFactory();
        list($providerId, $listenerId, $entryPointId) = $factory->create($container, $id, $config, $userProviderId, $defaultEntryPointId);

        return array($container, $entryPointId);
    }
}
