<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection;

use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SecurityExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage The check_path "/some_area/login_check" for login method "form_login" is not matched by the firewall pattern "/secured_area/.*".
     */
    public function testInvalidCheckPath()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/secured_area/.*',
                    'form_login' => array(
                        'check_path' => '/some_area/login_check',
                    ),
                ),
            ),
        ));

        $container->compile();
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage No authentication listener registered for firewall "some_firewall"
     */
    public function testFirewallWithoutAuthenticationListener()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/.*',
                ),
            ),
        ));

        $container->compile();
    }

    protected function getRawContainer()
    {
        $container = new ContainerBuilder();
        $security = new SecurityExtension();
        $container->registerExtension($security);

        $bundle = new SecurityBundle();
        $bundle->build($container);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());

        return $container;
    }

    protected function getContainer()
    {
        $container = $this->getRawContainer();
        $container->compile();

        return $container;
    }
}
