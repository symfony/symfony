<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Doctrine\Common\Annotations\AnnotationException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;

class SerializerMappingCheckerCommand extends Command
{
    protected static $defaultName = 'serializer:mapping:checker';
    private $loaders;

    /**
     * @param LoaderInterface[] $loaders The serializer metadata loaders
     */
    public function __construct(array $loaders = [])
    {
        parent::__construct();
        $this->loaders = $loaders;
    }

    protected function configure()
    {
        $this->setDescription('Validate serialization config.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists(CacheClassMetadataFactory::class) || !method_exists(XmlFileLoader::class, 'getMappedClasses') || !method_exists(YamlFileLoader::class, 'getMappedClasses')) {
            return false;
        }
        $io = new SymfonyStyle($input, $output);

        $arrayAdapter = new ArrayAdapter(0, false);

        $metadataFactory = new CacheClassMetadataFactory(new ClassMetadataFactory(new LoaderChain($this->loaders)), $arrayAdapter);

        foreach ($this->extractSupportedLoaders($this->loaders) as $loader) {
            foreach ($loader->getMappedClasses() as $mappedClass) {
                try {
                    $io->section($mappedClass);

                    $testClass = new \ReflectionClass($mappedClass);

                    $metadata = $metadataFactory->getMetadataFor($mappedClass);

                    $attributes = $this->extractClassAttributes($mappedClass);

                    foreach ($metadata->getAttributesMetadata() as $attributeMetadata) {
                        if (!\in_array($attributeMetadata->getName(), $attributes)) {
                            $io->warning(sprintf('error on %s::%s', $mappedClass, $attributeMetadata->getName()));
                        }
                    }
                } catch (AnnotationException $e) {
                    // ignore failing annotations
                } catch (\Exception $e) {
                    $io->error('An exception occurred: '.$e->getMessage());
                }
            }
        }

        return true;
    }

    /**
     * Duplicate form SerializerCacheWarmer.
     *
     * @param LoaderInterface[] $loaders
     *
     * @return XmlFileLoader[]|YamlFileLoader[]
     */
    private function extractSupportedLoaders(array $loaders): array
    {
        $supportedLoaders = [];

        foreach ($loaders as $loader) {
            if ($loader instanceof XmlFileLoader || $loader instanceof YamlFileLoader) {
                $supportedLoaders[] = $loader;
            } elseif ($loader instanceof LoaderChain) {
                $supportedLoaders = array_merge($supportedLoaders, $this->extractSupportedLoaders($loader->getLoaders()));
            }
        }

        return $supportedLoaders;
    }

    /**
     * Duplicate from ObjectNormalizer but by passing the class instead of an object
     * We can have abstract or not constructible object, so better use only class.
     */
    private function extractClassAttributes(string $class, $object = null, $format = 'json', $context = [])
    {
        // If not using groups, detect manually
        $attributes = [];

        // methods
        $reflClass = new \ReflectionClass($class);

        foreach ($reflClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflMethod) {
            if (
                0 !== $reflMethod->getNumberOfRequiredParameters() ||
                $reflMethod->isStatic() ||
                $reflMethod->isConstructor() ||
                $reflMethod->isDestructor()
            ) {
                continue;
            }

            $name = $reflMethod->name;
            $attributeName = null;

            if (0 === strpos($name, 'get') || 0 === strpos($name, 'has')) {
                // getters and hassers
                $attributeName = substr($name, 3);

                if (!$reflClass->hasProperty($attributeName)) {
                    $attributeName = lcfirst($attributeName);
                }
            } elseif (0 === strpos($name, 'is')) {
                // issers
                $attributeName = substr($name, 2);

                if (!$reflClass->hasProperty($attributeName)) {
                    $attributeName = lcfirst($attributeName);
                }
            }

            if (null !== $attributeName && $this->isAllowedAttribute(null, $attributeName, $format, $context)) {
                $attributes[$attributeName] = true;
            }
        }

        $checkPropertyInitialization = \PHP_VERSION_ID >= 70400;

        // properties
        foreach ($reflClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflProperty) {
            if ($checkPropertyInitialization && !$reflProperty->isInitialized($object)) {
                continue;
            }

            if ($reflProperty->isStatic() || !$this->isAllowedAttribute($object, $reflProperty->name, $format, $context)) {
                continue;
            }

            $attributes[$reflProperty->name] = true;
        }

        return array_keys($attributes);
    }

    /**
     * Duplicate from ObjectNormalizer but we have no context, so do nothing from now.
     *
     * Is this attribute allowed?
     *
     * @param object|string $classOrObject
     * @param string        $attribute
     * @param string|null   $format
     *
     * @return bool
     */
    protected function isAllowedAttribute($classOrObject, $attribute, $format = null, array $context = [])
    {
        // we can't have context here for now, so just bypass it
        return true;

//        $ignoredAttributes = $context[self::IGNORED_ATTRIBUTES] ?? $this->defaultContext[self::IGNORED_ATTRIBUTES] ?? $this->ignoredAttributes;
//        if (\in_array($attribute, $ignoredAttributes)) {
//            return false;
//        }
//
//        $attributes = $context[self::ATTRIBUTES] ?? $this->defaultContext[self::ATTRIBUTES] ?? null;
//        if (isset($attributes[$attribute])) {
//            // Nested attributes
//            return true;
//        }
//
//        if (\is_array($attributes)) {
//            return \in_array($attribute, $attributes, true);
//        }
//
//        return true;
    }
}
