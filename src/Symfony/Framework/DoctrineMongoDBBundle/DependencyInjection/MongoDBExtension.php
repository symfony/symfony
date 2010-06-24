<?php

namespace Symfony\Framework\DoctrineMongoDBBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\FileResource;

/**
 * Doctrine MongoDB ODM extension.
 *
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 *
 * @todo Add support for multiple document managers
 */
class MongoDBExtension extends LoaderExtension
{
    protected $bundles;
    protected $resources = array(
        'mongodb' => 'mongodb.xml',
    );

    public function __construct(array $bundles)
    {
        $this->bundles = $bundles;
    }

    public function mongodbLoad($config, BuilderConfiguration $configuration)
    {
        $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
        $configuration->merge($loader->load($this->resources['mongodb']));

        if (!$configuration->hasDefinition('doctrine.odm.mongodb.document_manager')) {

            $configuration->setParameter('doctrine.odm.mongodb.mapping_dirs', $this->findBundleSubpaths('Resources/config/doctrine/metadata', $configuration));
            $configuration->setParameter('doctrine.odm.mongodb.document_dirs', $this->findBundleSubpaths('Document', $configuration));

            $configuration->setDefinition('doctrine.odm.mongodb.metadata', $this->buildMetadataDefinition($configuration));
        }

        foreach (array('host', 'port', 'database') as $key) {
            if (isset($config[$key])) {
                $configuration->setParameter('doctrine.odm.mongodb.default_'.$key, $config[$key]);
            }
        }

        foreach (array('proxy_dir', 'auto_generate_proxy_classes') as $key) {
            if (isset($config[$key])) {
                $configuration->setParameter('doctrine.odm.mongodb.'.$key, $config[$key]);
            }
        }

        foreach (array('cache', 'metadata') as $key) {
            if (isset($config[$key])) {
                $configuration->setAlias('doctrine.odm.mongodb.'.$key, 'doctrine.odm.mongodb.'.$key.'.'.$config[$key]);
            }
        }

        return $configuration;
    }

    /**
     * Finds existing bundle subpaths.
     *
     * @param string $path A subpath to check for
     * @param Symfony\Components\DependencyInjection\BuilderConfiguration $configuration A builder configuration
     *
     * @return array An array of absolute directory paths
     */
    protected function findBundleSubpaths($path, BuilderConfiguration $configuration)
    {
        $dirs = array();
        foreach ($this->bundles as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            if (is_dir($dir = dirname($reflection->getFilename()).'/'.$path)) {
                $dirs[] = $dir;
                $configuration->addResource(new FileResource($dir));
            } else {
                // add the closest existing parent directory as a file resource
                do {
                    $dir = dirname($dir);
                } while (!is_dir($dir));
                $configuration->addResource(new FileResource($dir));
            }
        }

        return $dirs;
    }

    /**
     * Detects and builds the appropriate metadata driver for each bundle.
     *
     * @param Symfony\Components\DependencyInjection\BuilderConfiguration $configuration A builder configuration
     *
     * @return Symfony\Components\DependencyInjection\Definition A definition for the metadata service
     */
    protected function buildMetadataDefinition(BuilderConfiguration $configuration)
    {
        $definition = new Definition('%doctrine.odm.mongodb.metadata.chain_class%');

        foreach ($this->bundles as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            if ($driver = static::detectMetadataDriver(dirname($reflection->getFilename()), $configuration)) {
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
     * @param Symfony\Components\DependencyInjection\BuilderConfiguration $configuration A builder configuration
     *
     * @return string|null A metadata driver short name, if one can be detected
     */
    static protected function detectMetadataDriver($dir, BuilderConfiguration $configuration)
    {
        // add the closest existing directory as a resource
        $resource = $dir.'/Resources/config/doctrine/metadata';
        while (!is_dir($resource)) {
            $resource = dirname($resource);
        }
        $configuration->addResource(new FileResource($resource));

        if (count(glob($dir.'/Resources/config/doctrine/metadata/*.xml'))) {
            return 'xml';
        } elseif (count(glob($dir.'/Resources/config/doctrine/metadata/*.yml'))) {
            return 'yml';
        }

        // add the directory itself as a resource
        $configuration->addResource(new FileResource($dir));

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
        return __DIR__.'/../Resources/config';
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