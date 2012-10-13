<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Mapping\ClassMetadataFactory;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Mapping\BlackholeMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\Cache\CacheInterface;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\Mapping\Loader\YamlFilesLoader;
use Symfony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Validator\Mapping\Loader\XmlFilesLoader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\ArrayCache;

/**
 * The default implementation of {@link ValidatorBuilderInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidatorBuilder implements ValidatorBuilderInterface
{
    /**
     * @var array
     */
    private $initializers = array();

    /**
     * @var array
     */
    private $xmlMappings = array();

    /**
     * @var array
     */
    private $yamlMappings = array();

    /**
     * @var array
     */
    private $methodMappings = array();

    /**
     * @var Reader
     */
    private $annotationReader = null;

    /**
     * @var ClassMetadataFactoryInterface
     */
    private $metadataFactory;

    /**
     * @var ConstraintValidatorFactoryInterface
     */
    private $validatorFactory;

    /**
     * @var CacheInterface
     */
    private $metadataCache;

    /**
     * {@inheritdoc}
     */
    public function addObjectInitializer(ObjectInitializerInterface $initializer)
    {
        $this->initializers[] = $initializer;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addObjectInitializers(array $initializers)
    {
        $this->initializers = array_merge($this->initializers, $initializers);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addXmlMapping($path)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->xmlMappings[] = $path;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addXmlMappings(array $paths)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->xmlMappings = array_merge($this->xmlMappings, $paths);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addYamlMapping($path)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->yamlMappings[] = $path;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addYamlMappings(array $paths)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->yamlMappings = array_merge($this->yamlMappings, $paths);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addMethodMapping($methodName)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->methodMappings[] = $methodName;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addMethodMappings(array $methodNames)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->methodMappings = array_merge($this->methodMappings, $methodNames);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function enableAnnotationMapping(Reader $annotationReader = null)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot enable annotation mapping after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        if (null === $annotationReader) {
            if (!class_exists('Doctrine\Common\Annotations\AnnotationReader')) {
                throw new \RuntimeException('Requested a ValidatorFactory with an AnnotationLoader, but the AnnotationReader was not found. You should add Doctrine Common to your project.');
            }

            $annotationReader = new CachedReader(new AnnotationReader(), new ArrayCache());
        }

        $this->annotationReader = $annotationReader;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function disableAnnotationMapping()
    {
        $this->annotationReader = null;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setMetadataFactory(ClassMetadataFactoryInterface $metadataFactory)
    {
        if (count($this->xmlMappings) > 0 || count($this->yamlMappings) > 0 || count($this->methodMappings) > 0 || null !== $this->annotationReader) {
            throw new ValidatorException('You cannot set a custom metadata factory after adding custom mappings. You should do either of both.');
        }

        $this->metadataFactory = $metadataFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setMetadataCache(CacheInterface $cache)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot set a custom metadata cache after setting a custom metadata factory. Configure your metadata factory instead.');
        }
        
        $this->metadataCache = $cache;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setConstraintValidatorFactory(ConstraintValidatorFactoryInterface $validatorFactory)
    {
        $this->validatorFactory = $validatorFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidator()
    {
        $metadataFactory = $this->metadataFactory;

        if (!$metadataFactory) {
            $loaders = array();

            if (count($this->xmlMappings) > 1) {
                $loaders[] = new XmlFilesLoader($this->xmlMappings);
            } elseif (1 === count($this->xmlMappings)) {
                $loaders[] = new XmlFileLoader($this->xmlMappings[0]);
            }

            if (count($this->yamlMappings) > 1) {
                $loaders[] = new YamlFilesLoader($this->yamlMappings);
            } elseif (1 === count($this->yamlMappings)) {
                $loaders[] = new YamlFileLoader($this->yamlMappings[0]);
            }

            foreach ($this->methodMappings as $methodName) {
                $loaders[] = new StaticMethodLoader($methodName);
            }

            if ($this->annotationReader) {
                $loaders[] = new AnnotationLoader($this->annotationReader);
            }

            $loader = null;

            if (count($loaders) > 1) {
                $loader = new LoaderChain($loaders);
            } elseif (1 === count($loaders)) {
                $loader = $loaders[0];
            }

            $metadataFactory = new ClassMetadataFactory($loader, $this->metadataCache);
        }

        $validatorFactory = $this->validatorFactory ?: new ConstraintValidatorFactory();

        return new Validator($metadataFactory, $validatorFactory, $this->initializers);
    }
}
