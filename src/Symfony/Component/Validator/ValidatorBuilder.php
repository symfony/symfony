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
use Doctrine\Common\Annotations\PsrCachedReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Cache\ArrayCache;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use Symfony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

// Help opcache.preload discover always-needed symbols
class_exists(TranslatorInterface::class);
class_exists(LocaleAwareInterface::class);
class_exists(TranslatorTrait::class);

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidatorBuilder
{
    private $initializers = [];
    private $loaders = [];
    private $xmlMappings = [];
    private $yamlMappings = [];
    private $methodMappings = [];

    /**
     * @var Reader|null
     */
    private $annotationReader;
    private $enableAnnotationMapping = false;

    /**
     * @var MetadataFactoryInterface|null
     */
    private $metadataFactory;

    /**
     * @var ConstraintValidatorFactoryInterface|null
     */
    private $validatorFactory;

    /**
     * @var CacheItemPoolInterface|null
     */
    private $mappingCache;

    /**
     * @var TranslatorInterface|null
     */
    private $translator;

    /**
     * @var string|null
     */
    private $translationDomain;

    /**
     * Adds an object initializer to the validator.
     *
     * @return $this
     */
    public function addObjectInitializer(ObjectInitializerInterface $initializer)
    {
        $this->initializers[] = $initializer;

        return $this;
    }

    /**
     * Adds a list of object initializers to the validator.
     *
     * @param ObjectInitializerInterface[] $initializers
     *
     * @return $this
     */
    public function addObjectInitializers(array $initializers)
    {
        $this->initializers = array_merge($this->initializers, $initializers);

        return $this;
    }

    /**
     * Adds an XML constraint mapping file to the validator.
     *
     * @return $this
     */
    public function addXmlMapping(string $path)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->xmlMappings[] = $path;

        return $this;
    }

    /**
     * Adds a list of XML constraint mapping files to the validator.
     *
     * @param string[] $paths The paths to the mapping files
     *
     * @return $this
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
     * Adds a YAML constraint mapping file to the validator.
     *
     * @param string $path The path to the mapping file
     *
     * @return $this
     */
    public function addYamlMapping(string $path)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->yamlMappings[] = $path;

        return $this;
    }

    /**
     * Adds a list of YAML constraint mappings file to the validator.
     *
     * @param string[] $paths The paths to the mapping files
     *
     * @return $this
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
     * Enables constraint mapping using the given static method.
     *
     * @return $this
     */
    public function addMethodMapping(string $methodName)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->methodMappings[] = $methodName;

        return $this;
    }

    /**
     * Enables constraint mapping using the given static methods.
     *
     * @param string[] $methodNames The names of the methods
     *
     * @return $this
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
     * Enables annotation based constraint mapping.
     *
     * @param bool $skipDoctrineAnnotations
     *
     * @return $this
     */
    public function enableAnnotationMapping(/* bool $skipDoctrineAnnotations = true */)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot enable annotation mapping after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $skipDoctrineAnnotations = 1 > \func_num_args() ? false : func_get_arg(0);
        if (false === $skipDoctrineAnnotations || null === $skipDoctrineAnnotations) {
            trigger_deprecation('symfony/validator', '5.2', 'Not passing true as first argument to "%s" is deprecated. Pass true and call "addDefaultDoctrineAnnotationReader()" if you want to enable annotation mapping with Doctrine Annotations.', __METHOD__);
            $this->addDefaultDoctrineAnnotationReader();
        } elseif ($skipDoctrineAnnotations instanceof Reader) {
            trigger_deprecation('symfony/validator', '5.2', 'Passing an instance of "%s" as first argument to "%s" is deprecated. Pass true instead and call setDoctrineAnnotationReader() if you want to enable annotation mapping with Doctrine Annotations.', get_debug_type($skipDoctrineAnnotations), __METHOD__);
            $this->setDoctrineAnnotationReader($skipDoctrineAnnotations);
        } elseif (true !== $skipDoctrineAnnotations) {
            throw new \TypeError(sprintf('"%s": Argument 1 is expected to be a boolean, "%s" given.', __METHOD__, get_debug_type($skipDoctrineAnnotations)));
        }

        $this->enableAnnotationMapping = true;

        return $this;
    }

    /**
     * Disables annotation based constraint mapping.
     *
     * @return $this
     */
    public function disableAnnotationMapping()
    {
        $this->enableAnnotationMapping = false;
        $this->annotationReader = null;

        return $this;
    }

    /**
     * @return $this
     */
    public function setDoctrineAnnotationReader(?Reader $reader): self
    {
        $this->annotationReader = $reader;

        return $this;
    }

    /**
     * @return $this
     */
    public function addDefaultDoctrineAnnotationReader(): self
    {
        $this->annotationReader = $this->createAnnotationReader();

        return $this;
    }

    /**
     * Sets the class metadata factory used by the validator.
     *
     * @return $this
     */
    public function setMetadataFactory(MetadataFactoryInterface $metadataFactory)
    {
        if (\count($this->xmlMappings) > 0 || \count($this->yamlMappings) > 0 || \count($this->methodMappings) > 0 || $this->enableAnnotationMapping) {
            throw new ValidatorException('You cannot set a custom metadata factory after adding custom mappings. You should do either of both.');
        }

        $this->metadataFactory = $metadataFactory;

        return $this;
    }

    /**
     * Sets the cache for caching class metadata.
     *
     * @return $this
     */
    public function setMappingCache(CacheItemPoolInterface $cache)
    {
        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot set a custom mapping cache after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->mappingCache = $cache;

        return $this;
    }

    /**
     * Sets the constraint validator factory used by the validator.
     *
     * @return $this
     */
    public function setConstraintValidatorFactory(ConstraintValidatorFactoryInterface $validatorFactory)
    {
        $this->validatorFactory = $validatorFactory;

        return $this;
    }

    /**
     * Sets the translator used for translating violation messages.
     *
     * @return $this
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;

        return $this;
    }

    /**
     * Sets the default translation domain of violation messages.
     *
     * The same message can have different translations in different domains.
     * Pass the domain that is used for violation messages by default to this
     * method.
     *
     * @return $this
     */
    public function setTranslationDomain(?string $translationDomain)
    {
        $this->translationDomain = $translationDomain;

        return $this;
    }

    /**
     * @return $this
     */
    public function addLoader(LoaderInterface $loader)
    {
        $this->loaders[] = $loader;

        return $this;
    }

    /**
     * @return LoaderInterface[]
     */
    public function getLoaders()
    {
        $loaders = [];

        foreach ($this->xmlMappings as $xmlMapping) {
            $loaders[] = new XmlFileLoader($xmlMapping);
        }

        foreach ($this->yamlMappings as $yamlMappings) {
            $loaders[] = new YamlFileLoader($yamlMappings);
        }

        foreach ($this->methodMappings as $methodName) {
            $loaders[] = new StaticMethodLoader($methodName);
        }

        if ($this->enableAnnotationMapping) {
            $loaders[] = new AnnotationLoader($this->annotationReader);
        }

        return array_merge($loaders, $this->loaders);
    }

    /**
     * Builds and returns a new validator object.
     *
     * @return ValidatorInterface
     */
    public function getValidator()
    {
        $metadataFactory = $this->metadataFactory;

        if (!$metadataFactory) {
            $loaders = $this->getLoaders();
            $loader = null;

            if (\count($loaders) > 1) {
                $loader = new LoaderChain($loaders);
            } elseif (1 === \count($loaders)) {
                $loader = $loaders[0];
            }

            $metadataFactory = new LazyLoadingMetadataFactory($loader, $this->mappingCache);
        }

        $validatorFactory = $this->validatorFactory ?? new ConstraintValidatorFactory();
        $translator = $this->translator;

        if (null === $translator) {
            $translator = new class() implements TranslatorInterface, LocaleAwareInterface {
                use TranslatorTrait;
            };
            // Force the locale to be 'en' when no translator is provided rather than relying on the Intl default locale
            // This avoids depending on Intl or the stub implementation being available. It also ensures that Symfony
            // validation messages are pluralized properly even when the default locale gets changed because they are in
            // English.
            $translator->setLocale('en');
        }

        $contextFactory = new ExecutionContextFactory($translator, $this->translationDomain);

        return new RecursiveValidator($contextFactory, $metadataFactory, $validatorFactory, $this->initializers);
    }

    private function createAnnotationReader(): Reader
    {
        if (!class_exists(AnnotationReader::class)) {
            throw new LogicException('Enabling annotation based constraint mapping requires the packages doctrine/annotations and symfony/cache to be installed.');
        }

        if (class_exists(ArrayAdapter::class)) {
            return new PsrCachedReader(new AnnotationReader(), new ArrayAdapter());
        }

        if (class_exists(CachedReader::class) && class_exists(ArrayCache::class)) {
            trigger_deprecation('symfony/validator', '5.4', 'Enabling annotation based constraint mapping without having symfony/cache installed is deprecated.');

            return new CachedReader(new AnnotationReader(), new ArrayCache());
        }

        throw new LogicException('Enabling annotation based constraint mapping requires the packages doctrine/annotations and symfony/cache to be installed.');
    }
}
