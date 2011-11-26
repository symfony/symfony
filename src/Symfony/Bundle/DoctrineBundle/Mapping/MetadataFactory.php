<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Mapping;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;

/**
 * This class provides methods to access Doctrine entity class metadata for a
 * given bundle, namespace or entity class.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MetadataFactory
{
    private $registry;

    /**
     * Constructor.
     *
     * @param RegistryInterface $registry A RegistryInterface instance
     */
    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Gets the metadata of all classes of a bundle.
     *
     * @param BundleInterface $bundle A BundleInterface instance
     *
     * @return ClassMetadataCollection A ClassMetadataCollection instance
     */
    public function getBundleMetadata(BundleInterface $bundle)
    {
        $namespace = $bundle->getNamespace();
        $metadata = $this->getMetadataForNamespace($namespace);
        if (!$metadata->getMetadata()) {
            throw new \RuntimeException(sprintf('Bundle "%s" does not contain any mapped entities.', $bundle->getName()));
        }

        $path = $this->getBasePathForClass($bundle->getName(), $bundle->getNamespace(), $bundle->getPath());

        $metadata->setPath($path);
        $metadata->setNamespace($bundle->getNamespace());

        return $metadata;
    }

    /**
     * Gets the metadata of a class.
     *
     * @param string $class A class name
     * @param string $path  The path where the class is stored (if known)
     *
     * @return ClassMetadataCollection A ClassMetadataCollection instance
     */
    public function getClassMetadata($class, $path = null)
    {
        $metadata = $this->getMetadataForClass($class);
        if (!$metadata->getMetadata()) {
            throw MappingException::classIsNotAValidEntityOrMappedSuperClass($class);
        }

        $all = $metadata->getMetadata();
        if (class_exists($class)) {
            $r = $all[0]->getReflectionClass();
            $path = $this->getBasePathForClass($class, $r->getNamespacename(), dirname($r->getFilename()));
        } elseif (!$path) {
            throw new \RuntimeException(sprintf('Unable to determine where to save the "%s" class (use the --path option).', $class));
        }

        $metadata->setPath($path);
        $metadata->setNamespace($r->getNamespacename());

        return $metadata;
    }

    /**
     * Gets the metadata of all classes of a namespace.
     *
     * @param string $namespace A namespace name
     * @param string $path      The path where the class is stored (if known)
     *
     * @return ClassMetadataCollection A ClassMetadataCollection instance
     */
    public function getNamespaceMetadata($namespace, $path = null)
    {
        $metadata = $this->getMetadataForNamespace($namespace);
        if (!$metadata->getMetadata()) {
            throw new \RuntimeException(sprintf('Namespace "%s" does not contain any mapped entities.', $namespace));
        }

        $all = $metadata->getMetadata();
        if (class_exists($all[0]->name)) {
            $r = $all[0]->getReflectionClass();
            $path = $this->getBasePathForClass($namespace, $r->getNamespacename(), dirname($r->getFilename()));
        } elseif (!$path) {
            throw new \RuntimeException(sprintf('Unable to determine where to save the "%s" class (use the --path option).', $all[0]->name));
        }

        $metadata->setPath($path);
        $metadata->setNamespace($namespace);

        return $metadata;
    }

    private function getBasePathForClass($name, $namespace, $path)
    {
        $namespace = str_replace('\\', '/', $namespace);
        $search = str_replace('\\', '/', $path);
        $destination = str_replace('/'.$namespace, '', $search, $c);

        if ($c != 1) {
            throw new \RuntimeException(sprintf('Can\'t find base path for "%s" (path: "%s", destination: "%s").', $name, $path, $destination));
        }

        return $destination;
    }

    private function getMetadataForNamespace($namespace)
    {
        $metadata = array();
        foreach ($this->getAllMetadata() as $m) {
            if (strpos($m->name, $namespace) === 0) {
                $metadata[] = $m;
            }
        }

        return new ClassMetadataCollection($metadata);
    }

    private function getMetadataForClass($entity)
    {
        foreach ($this->getAllMetadata() as $metadata) {
            if ($metadata->name === $entity) {
                return new ClassMetadataCollection(array($metadata));
            }
        }

        return new ClassMetadataCollection(array());
    }

    private function getAllMetadata()
    {
        $metadata = array();
        foreach ($this->registry->getEntityManagers() as $em) {
            $class = $this->getClassMetadataFactoryClass();
            $cmf = new $class();
            $cmf->setEntityManager($em);
            foreach ($cmf->getAllMetadata() as $m) {
                $metadata[] = $m;
            }
        }

        return $metadata;
    }

    protected function getClassMetadataFactoryClass()
    {
        return 'Doctrine\\ORM\\Mapping\\ClassMetadataFactory';
    }
}
