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

use Symfony\Component\Validator\Mapping\Cache\CacheInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Doctrine\Common\Annotations\Reader;

/**
 * A configurable builder for ValidatorInterface objects.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ValidatorBuilderInterface
{
    /**
     * Adds an object initializer to the validator.
     *
     * @param ObjectInitializerInterface $initializer The initializer.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.1.0
     */
    public function addObjectInitializer(ObjectInitializerInterface $initializer);

    /**
     * Adds a list of object initializers to the validator.
     *
     * @param array $initializers The initializer.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.1.0
     */
    public function addObjectInitializers(array $initializers);

    /**
     * Adds an XML constraint mapping file to the validator.
     *
     * @param string $path The path to the mapping file.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.1.0
     */
    public function addXmlMapping($path);

    /**
     * Adds a list of XML constraint mapping files to the validator.
     *
     * @param array $paths The paths to the mapping files.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.1.0
     */
    public function addXmlMappings(array $paths);

    /**
     * Adds a YAML constraint mapping file to the validator.
     *
     * @param string $path The path to the mapping file.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.1.0
     */
    public function addYamlMapping($path);

    /**
     * Adds a list of YAML constraint mappings file to the validator.
     *
     * @param array $paths The paths to the mapping files.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.1.0
     */
    public function addYamlMappings(array $paths);

    /**
     * Enables constraint mapping using the given static method.
     *
     * @param string $methodName The name of the method.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.1.0
     */
    public function addMethodMapping($methodName);

    /**
     * Enables constraint mapping using the given static methods.
     *
     * @param array $methodNames The names of the methods.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.1.0
     */
    public function addMethodMappings(array $methodNames);

    /**
     * Enables annotation based constraint mapping.
     *
     * @param Reader $annotationReader The annotation reader to be used.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.1.0
     */
    public function enableAnnotationMapping(Reader $annotationReader = null);

    /**
     * Disables annotation based constraint mapping.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.1.0
     */
    public function disableAnnotationMapping();

    /**
     * Sets the class metadata factory used by the validator.
     *
     * @param MetadataFactoryInterface $metadataFactory The metadata factory.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.3.0
     */
    public function setMetadataFactory(MetadataFactoryInterface $metadataFactory);

    /**
     * Sets the cache for caching class metadata.
     *
     * @param CacheInterface $cache The cache instance.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.1.0
     */
    public function setMetadataCache(CacheInterface $cache);

    /**
     * Sets the constraint validator factory used by the validator.
     *
     * @param ConstraintValidatorFactoryInterface $validatorFactory The validator factory.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.1.0
     */
    public function setConstraintValidatorFactory(ConstraintValidatorFactoryInterface $validatorFactory);

    /**
     * Sets the translator used for translating violation messages.
     *
     * @param TranslatorInterface $translator The translator instance.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.2.0
     */
    public function setTranslator(TranslatorInterface $translator);

    /**
     * Sets the default translation domain of violation messages.
     *
     * The same message can have different translations in different domains.
     * Pass the domain that is used for violation messages by default to this
     * method.
     *
     * @param string $translationDomain The translation domain of the violation messages.
     *
     * @return ValidatorBuilderInterface The builder object.
     *
     * @since v2.2.0
     */
    public function setTranslationDomain($translationDomain);

    /**
     * Builds and returns a new validator object.
     *
     * @return ValidatorInterface The built validator.
     *
     * @since v2.1.0
     */
    public function getValidator();
}
