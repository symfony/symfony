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
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Mapping\Cache\CacheInterface;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use Symfony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Validator\Util\LegacyTranslatorProxy;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

// Help opcache.preload discover always-needed symbols
class_exists(TranslatorInterface::class);
class_exists(LocaleAwareInterface::class);
class_exists(TranslatorTrait::class);

/**
 * The default implementation of {@link ValidatorBuilderInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidatorBuilder implements ValidatorBuilderInterface
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
            if (!class_exists(AnnotationReader::class) || !class_exists(ArrayCache::class)) {
                throw new LogicException('Enabling annotation based constraint mapping requires the packages doctrine/annotations and doctrine/cache to be installed.');
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
        if (\count($this->xmlMappings) > 0 || \count($this->yamlMappings) > 0 || \count($this->methodMappings) > 0 || null !== $this->annotationReader) {
            throw new ValidatorException('You cannot set a custom metadata factory after adding custom mappings. You should do either of both.');
        }

        $this->metadataFactory = $metadataFactory;

        return $this;
    }

    /**
     * Sets the cache for caching class metadata.
     *
     * @return $this
     *
     * @deprecated since Symfony 4.4.
     */
    public function setMetadataCache(CacheInterface $cache)
    {
        @trigger_error(sprintf('%s is deprecated since Symfony 4.4. Use setMappingCache() instead.', __METHOD__), E_USER_DEPRECATED);

        if (null !== $this->metadataFactory) {
            throw new ValidatorException('You cannot set a custom metadata cache after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->mappingCache = $cache;

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
     * {@inheritdoc}
     */
    public function setConstraintValidatorFactory(ConstraintValidatorFactoryInterface $validatorFactory)
    {
        $this->validatorFactory = $validatorFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @final since Symfony 4.2
     */
    public function setTranslator(LegacyTranslatorInterface $translator)
    {
        $this->translator = $translator;

        while ($this->translator instanceof LegacyTranslatorProxy) {
            $this->translator = $this->translator->getTranslator();
        }

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

        if ($this->annotationReader) {
            $loaders[] = new AnnotationLoader($this->annotationReader);
        }

        return array_merge($loaders, $this->loaders);
    }

    /**
     * {@inheritdoc}
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

        $validatorFactory = $this->validatorFactory ?: new ConstraintValidatorFactory();
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
}
