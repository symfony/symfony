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

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Cache\ArrayCache;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Context\LegacyExecutionContextFactory;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Mapping\Cache\CacheInterface;
use Symfony\Component\Validator\Mapping\ClassMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use Symfony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Validator\Mapping\Loader\XmlFilesLoader;
use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Validator\Mapping\Loader\YamlFilesLoader;
use Symfony\Component\Validator\Validator\LegacyValidator;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Component\Validator\Validator as ValidatorV24;

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
     * @var Reader|null
     */
    private $annotationReader;

    /**
     * @var MetadataFactoryInterface|null
     */
    private $metadataFactory;

    /**
     * @var ConstraintValidatorFactoryInterface|null
     */
    private $validatorFactory;

    /**
     * @var CacheInterface|null
     */
    private $metadataCache;

    /**
     * @var TranslatorInterface|null
     */
    private $translator;

    /**
     * @var null|string
     */
    private $translationDomain;

    /**
     * @var PropertyAccessorInterface|null
     */
    private $propertyAccessor;

    /**
     * @var int|null
     */
    private $apiVersion;

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
            if (!class_exists('Doctrine\Common\Annotations\AnnotationReader') || !class_exists('Doctrine\Common\Cache\ArrayCache')) {
                throw new \RuntimeException('Enabling annotation based constraint mapping requires the packages doctrine/annotations and doctrine/cache to be installed.');
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
    public function setMetadataFactory(MetadataFactoryInterface $metadataFactory)
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
        if (null !== $this->propertyAccessor) {
            throw new ValidatorException('You cannot set a validator factory after setting a custom property accessor. Remove the call to setPropertyAccessor() if you want to call setConstraintValidatorFactory().');
        }

        $this->validatorFactory = $validatorFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTranslationDomain($translationDomain)
    {
        $this->translationDomain = $translationDomain;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPropertyAccessor(PropertyAccessorInterface $propertyAccessor)
    {
        if (null !== $this->validatorFactory) {
            throw new ValidatorException('You cannot set a property accessor after setting a custom validator factory. Configure your validator factory instead.');
        }

        $this->propertyAccessor = $propertyAccessor;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setApiVersion($apiVersion)
    {
        if (!in_array($apiVersion, array(Validation::API_VERSION_2_4, Validation::API_VERSION_2_5, Validation::API_VERSION_2_5_BC))) {
            throw new InvalidArgumentException(sprintf(
                'The requested API version is invalid: "%s"',
                $apiVersion
            ));
        }

        if (PHP_VERSION_ID < 50309 && $apiVersion === Validation::API_VERSION_2_5_BC) {
            throw new InvalidArgumentException(sprintf(
                'The Validator API that is compatible with both Symfony 2.4 '.
                'and Symfony 2.5 can only be used on PHP 5.3.9 and higher. '.
                'Your current PHP version is %s.',
                PHP_VERSION
            ));
        }

        $this->apiVersion = $apiVersion;

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

        $validatorFactory = $this->validatorFactory ?: new ConstraintValidatorFactory($this->propertyAccessor);
        $translator = $this->translator ?: new DefaultTranslator();
        $apiVersion = $this->apiVersion;

        if (null === $apiVersion) {
            $apiVersion = PHP_VERSION_ID < 50309
                ? Validation::API_VERSION_2_4
                : Validation::API_VERSION_2_5_BC;
        }

        if (Validation::API_VERSION_2_4 === $apiVersion) {
            return new ValidatorV24($metadataFactory, $validatorFactory, $translator, $this->translationDomain, $this->initializers);
        }

        if (Validation::API_VERSION_2_5 === $apiVersion) {
            $contextFactory = new ExecutionContextFactory($translator, $this->translationDomain);

            return new RecursiveValidator($contextFactory, $metadataFactory, $validatorFactory, $this->initializers);
        }

        $contextFactory = new LegacyExecutionContextFactory($metadataFactory, $translator, $this->translationDomain);

        return new LegacyValidator($contextFactory, $metadataFactory, $validatorFactory, $this->initializers);
    }
}
