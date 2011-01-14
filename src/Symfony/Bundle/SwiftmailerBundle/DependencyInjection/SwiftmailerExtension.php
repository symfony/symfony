<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * SwiftMailerExtension is an extension for the SwiftMailer library.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SwiftMailerExtension extends Extension
{
    public function configLoad(array $configs, ContainerBuilder $container)
    {
        foreach ($configs as $config) {
            $this->doConfigLoad($config, $container);
        }
    }

    /**
     * Loads the Swift Mailer configuration.
     *
     * Usage example:
     *
     *      <swiftmailer:config transport="gmail">
     *        <swiftmailer:username>fabien</swift:username>
     *        <swiftmailer:password>xxxxx</swift:password>
     *        <swiftmailer:spool path="/path/to/spool/" />
     *      </swiftmailer:config>
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function doConfigLoad(array $config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('swiftmailer.mailer')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load('swiftmailer.xml');
            $container->setAlias('mailer', 'swiftmailer.mailer');
        }

        $r = new \ReflectionClass('Swift_Message');
        $container->setParameter('swiftmailer.base_dir', dirname(dirname(dirname($r->getFilename()))));

        $transport = $container->getParameter('swiftmailer.transport.name');
        if (array_key_exists('transport', $config)) {
            if (null === $config['transport']) {
                $transport = 'null';
            } elseif ('gmail' === $config['transport']) {
                $config['encryption'] = 'ssl';
                $config['auth_mode'] = 'login';
                $config['host'] = 'smtp.gmail.com';
                $transport = 'smtp';
            } else {
                $transport = $config['transport'];
            }

            $container->setParameter('swiftmailer.transport.name', $transport);
        }

        $container->setAlias('swiftmailer.transport', 'swiftmailer.transport.'.$transport);

        if (isset($config['encryption']) && 'ssl' === $config['encryption'] && !isset($config['port'])) {
            $config['port'] = 465;
        }

        foreach (array('encryption', 'port', 'host', 'username', 'password', 'auth_mode') as $key) {
            if (isset($config[$key])) {
                $container->setParameter('swiftmailer.transport.'.$transport.'.'.$key, $config[$key]);
            }
        }

        // spool?
        if (isset($config['spool'])) {
            $type = isset($config['spool']['type']) ? $config['spool']['type'] : 'file';

            $container->setAlias('swiftmailer.transport.real', 'swiftmailer.transport.'.$transport);
            $container->setAlias('swiftmailer.transport', 'swiftmailer.transport.spool');
            $container->setAlias('swiftmailer.spool', 'swiftmailer.spool.'.$type);

            foreach (array('path') as $key) {
                if (isset($config['spool'][$key])) {
                    $container->setParameter('swiftmailer.spool.'.$type.'.'.$key, $config['spool'][$key]);
                }
            }
        }

        if (array_key_exists('delivery-address', $config)) {
            $config['delivery_address'] = $config['delivery-address'];
        }

        if (isset($config['delivery_address']) && $config['delivery_address']) {
            $container->setParameter('swiftmailer.single_address', $config['delivery_address']);
            $container->findDefinition('swiftmailer.transport')->addMethodCall('registerPlugin', array(new Reference('swiftmailer.plugin.redirecting')));
        } else {
            $container->setParameter('swiftmailer.single_address', null);
        }

        if (array_key_exists('disable-delivery', $config)) {
            $config['disable_delivery'] = $config['disable-delivery'];
        }

        if (isset($config['disable_delivery']) && $config['disable_delivery']) {
            $container->findDefinition('swiftmailer.transport')->addMethodCall('registerPlugin', array(new Reference('swiftmailer.plugin.blackhole')));
        }
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    /**
     * Returns the namespace to be used for this extension (XML namespace).
     *
     * @return string The XML namespace
     */
    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/swiftmailer';
    }

    /**
     * Returns the recommended alias to use in XML.
     *
     * This alias is also the mandatory prefix to use when using YAML.
     *
     * @return string The alias
     */
    public function getAlias()
    {
        return 'swiftmailer';
    }
}
