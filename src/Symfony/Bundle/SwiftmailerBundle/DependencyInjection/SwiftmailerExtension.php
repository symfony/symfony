<?php

namespace Symfony\Bundle\SwiftmailerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * SwiftMailerExtension is an extension for the SwiftMailer library.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SwiftMailerExtension extends Extension
{
    protected $resources = array(
        'mailer' => 'swiftmailer.xml',
    );

    /**
     * Loads the Swift Mailer configuration.
     *
     * Usage example:
     *
     *      <swift:mailer transport="gmail" delivery_strategy="spool">
     *        <swift:username>fabien</swift:username>
     *        <swift:password>xxxxx</swift:password>
     *        <swift:spool path="/path/to/spool/" />
     *      </swift:mailer>
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function mailerLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('swiftmailer.mailer')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load($this->resources['mailer']);
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
            $type = isset($config['type']) ? $config['type'] : 'file';

            $container->setAlias('swiftmailer.transport.real', 'swiftmailer.transport.'.$transport);
            $container->setAlias('swiftmailer.transport', 'swiftmailer.transport.spool');
            $container->setAlias('swiftmailer.spool', 'swiftmailer.spool.'.$type);

            foreach (array('path') as $key) {
                if (isset($config['spool'][$key])) {
                    $container->setParameter('swiftmailer.spool.'.$type.'.'.$key, $config['spool'][$key]);
                }
            }
        }

        if (isset($config['delivery_address'])) {
            $container->setParameter('swiftmailer.single_address', $config['delivery_address']);
            $container->findDefinition('swiftmailer.transport')->addMethodCall('registerPlugin', array(new Reference('swiftmailer.plugin.redirecting')));
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
        return 'swift';
    }
}
