<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;

/**
 * SwiftmailerExtension is an extension for the SwiftMailer library.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SwiftmailerExtension extends Extension
{
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
     * @param array            $configs   An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('swiftmailer.xml');
        $container->setAlias('mailer', 'swiftmailer.mailer');

        $configuration = new Configuration($container->getParameter('kernel.debug'));
        $config = $this->processConfiguration($configuration, $configs);

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

        if (isset($config['disable_delivery']) && $config['disable_delivery']) {
            $transport = 'null';
        }

        if ('smtp' === $transport) {
            $loader->load('smtp.xml');
        }

        if (in_array($transport, array('smtp', 'mail', 'sendmail', 'null'))) {
            // built-in transport
            $transport = 'swiftmailer.transport.'.$transport;
        }

        $container->setAlias('swiftmailer.transport', $transport);

        if (false === $config['port']) {
            $config['port'] = 'ssl' === $config['encryption'] ? 465 : 25;
        }

        foreach (array('encryption', 'port', 'host', 'username', 'password', 'auth_mode') as $key) {
            $container->setParameter('swiftmailer.transport.smtp.'.$key, $config[$key]);
        }

        // spool?
        if (isset($config['spool'])) {
            $type = $config['spool']['type'];

            $loader->load('spool.xml');
            if ($type === 'file') {
                $loader->load('spool_file.xml');
            }
            $container->setAlias('swiftmailer.transport.real', $transport);
            $container->setAlias('swiftmailer.transport', 'swiftmailer.transport.spool');
            $container->setAlias('swiftmailer.spool', 'swiftmailer.spool.'.$type);

            foreach (array('path') as $key) {
                $container->setParameter('swiftmailer.spool.'.$type.'.'.$key, $config['spool'][$key]);
            }
        }
        $container->setParameter('swiftmailer.spool.enabled', isset($config['spool']));

        // antiflood?
        if (isset($config['antiflood'])) {
            $container->setParameter('swiftmailer.plugin.antiflood.threshold', $config['antiflood']['threshold']);
            $container->setParameter('swiftmailer.plugin.antiflood.sleep', $config['antiflood']['sleep']);

            $container->getDefinition('swiftmailer.plugin.antiflood')->addTag('swiftmailer.plugin');
        }

        if ($config['logging']) {
            $container->getDefinition('swiftmailer.plugin.messagelogger')->addTag('swiftmailer.plugin');
            $container->findDefinition('swiftmailer.data_collector')->addTag('data_collector', array('template' => 'SwiftmailerBundle:Collector:swiftmailer', 'id' => 'swiftmailer'));
        }

        if (isset($config['sender_address']) && $config['sender_address']) {
            $container->setParameter('swiftmailer.sender_address', $config['sender_address']);
            $container->getDefinition('swiftmailer.plugin.impersonate')->addTag('swiftmailer.plugin');
        } else {
            $container->setParameter('swiftmailer.sender_address', null);
        }

        if (isset($config['delivery_address']) && $config['delivery_address']) {
            $container->setParameter('swiftmailer.single_address', $config['delivery_address']);
            $container->getDefinition('swiftmailer.plugin.redirecting')->addTag('swiftmailer.plugin');
        } else {
            $container->setParameter('swiftmailer.single_address', null);
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
        return 'http://symfony.com/schema/dic/swiftmailer';
    }
}
