<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Extension\Extension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Resource\FileResource;

/**
 * Doctrine MongoDB ODM extension.
 *
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 *
 * @todo Add support for multiple document managers
 */
class MongoDBExtension extends Extension
{
    protected $bundles;
    protected $resources = array(
        'mongodb' => 'mongodb.xml',
    );
    protected $kernelCacheDir;

    public function __construct(array $bundles, $kernelCacheDir)
    {
        $this->bundles = $bundles;
        $this->kernelCacheDir = $kernelCacheDir;
    }

    /**
     * Loads the MongoDB configuration.
     *
     * @param array                                                        $config        An array of configuration settings
     * @param \Symfony\Components\DependencyInjection\ContainerBuilder $container A ContainerBuilder instance
     */
    public function mongodbLoad($config, ContainerBuilder $container)
    {
        $proxyCacheDir = $this->kernelCacheDir . '/doctrine/mongodb-odm/Proxies';
        if (!is_dir($proxyCacheDir)) {
            if (false === @mkdir($proxyCacheDir, 0777, true)) {
                die(sprintf('Unable to create the Doctrine Proxy directory (%s)', dirname($proxyCacheDir)));
            }
        } elseif (!is_writable($proxyCacheDir)) {
            die(sprintf('Unable to write in the Doctrine Proxy directory (%s)', $proxyCacheDir));
        }

        if (!$container->hasDefinition('doctrine.odm.mongodb.document_manager')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load($this->resources['mongodb']);

            $container->setParameter('doctrine.odm.mongodb.mapping_dirs', $this->findBundleSubpaths('Resources/config/doctrine/metadata', $container));
            $container->setParameter('doctrine.odm.mongodb.document_dirs', $this->findBundleSubpaths('Document', $container));

            $container->setDefinition('doctrine.odm.mongodb.metadata', $this->buildMetadataDefinition($container));
        }

        foreach (array('host', 'port', 'database') as $key) {
            if (isset($config[$key])) {
                $container->setParameter('doctrine.odm.mongodb.default_'.$key, $config[$key]);
            }
        }

        foreach (array('proxy_dir', 'auto_generate_proxy_classes') as $key) {
            if (isset($config[$key])) {
                $container->setParameter('doctrine.odm.mongodb.'.$key, $config[$key]);
            }
        }

        foreach (array('cache', 'metadata') as $key) {
            if (isset($config[$key])) {
                $container->setAlias('doctrine.odm.mongodb.'.$key, 'doctrine.odm.mongodb.'.$key.'.'.$config[$key]);
            }
        }
    }

    /**
     * Finds existing bundle subpaths.
     *
     * @param string $path A subpath to check for
     * @param Symfony\Components\DependencyInjection\ContainerBuilder $container A ContainerBuilder configuration
     *
     * @return array An array of absolute directory paths
     */
    protected function findBundleSubpaths($path, ContainerBuilder $container)
    {
        $dirs = array();
        foreach ($this->bundles as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            if (is_dir($dir = dirname($reflection->getFilename()).'/'.$path)) {
                $dirs[] = $dir;
                $container->addResource(new FileResource($dir));
            } else {
                // add the closest existing parent directory as a file resource
                do {
                    $dir = dirname($dir);
                } while (!is_dir($dir));
                $container->addResource(new FileResource($dir));
            }
        }

        return $dirs;
    }

    /**
     * Detects and builds the appropriate metadata driver for each bundle.
     *
     * @param Symfony\Components\DependencyInjection\ContainerBuilder $container A ContainerBuilder configuration
     *
     * @return Symfony\Components\DependencyInjection\Definition A definition for the metadata service
     */
    protected function buildMetadataDefinition(ContainerBuilder $container)
    {
        $definition = new Definition('%doctrine.odm.mongodb.metadata.chain_class%');

        foreach ($this->bundles as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            if ($driver = static::detectMetadataDriver(dirname($reflection->getFilename()), $container)) {
                $definition->addMethodCall('addDriver', array(
                    new Reference('doctrine.odm.mongodb.metadata.'.$driver),
                    $reflection->getNamespaceName().'\\Document',
                ));
            }
        }

        return $definition;
    }

    /**
     * Detects what metadata driver to use for the supplied directory.
     *
     * @param string $dir A directory path
     * @param Symfony\Components\DependencyInjection\ContainerBuilder $container A ContainerBuilder configuration
     *
     * @return string|null A metadata driver short name, if one can be detected
     */
    static protected function detectMetadataDriver($dir, ContainerBuilder $container)
    {
        // add the closest existing directory as a resource
        $resource = $dir.'/Resources/config/doctrine/metadata';
        while (!is_dir($resource)) {
            $resource = dirname($resource);
        }
        $container->addResource(new FileResource($resource));

        if (count(glob($dir.'/Resources/config/doctrine/metadata/*.xml'))) {
            return 'xml';
        } elseif (count(glob($dir.'/Resources/config/doctrine/metadata/*.yml'))) {
            return 'yml';
        }

        // add the directory itself as a resource
        $container->addResource(new FileResource($dir));

        if (is_dir($dir.'/Document')) {
            return 'annotation';
        }
    }

    /**
     * Returns the namespace to be used for this extension (XML namespace).
     *
     * @return string The XML namespace
     */
    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/doctrine/odm/mongodb';
    }

    /**
     * @return string
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
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
        return 'doctrine_odm';
    }
}