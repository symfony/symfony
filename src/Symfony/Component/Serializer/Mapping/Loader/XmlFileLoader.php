<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping\Loader;

use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;

/**
 * Loads XML mapping files.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class XmlFileLoader extends FileLoader
{
    /**
     * An array of {@class \SimpleXMLElement} instances.
     *
     * @var \SimpleXMLElement[]|null
     */
    private $classes;

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadataInterface $classMetadata)
    {
        if (null === $this->classes) {
            $this->classes = $this->getClassesFromXml();
        }

        if (!$this->classes) {
            return false;
        }

        $attributesMetadata = $classMetadata->getAttributesMetadata();

        if (isset($this->classes[$classMetadata->getName()])) {
            $xml = $this->classes[$classMetadata->getName()];

            foreach ($xml->attribute as $attribute) {
                $attributeName = (string) $attribute['name'];

                if (isset($attributesMetadata[$attributeName])) {
                    $attributeMetadata = $attributesMetadata[$attributeName];
                } else {
                    $attributeMetadata = new AttributeMetadata($attributeName);
                    $classMetadata->addAttributeMetadata($attributeMetadata);
                }

                foreach ($attribute->group as $group) {
                    $attributeMetadata->addGroup((string) $group);
                }

                foreach ($attribute->methods as $methods) {
                    if (isset($methods['accessor'])) {
                        $attributeMetadata->setMethodsAccessor((string) $methods['accessor']);
                    }

                    if (isset($methods['mutator'])) {
                        $attributeMetadata->setMethodsMutator((string) $methods['mutator']);
                    }
                }

                if (isset($attribute['exclude'])) {
                    $attributeMetadata->setExclude((bool) $attribute['exclude']);
                }

                if (isset($attribute['expose'])) {
                    $attributeMetadata->setExpose((bool) $attribute['expose']);
                }

                if (isset($attribute['max-depth'])) {
                    $attributeMetadata->setMaxDepth((int) $attribute['max-depth']);
                }

                if (isset($attribute['read-only'])) {
                    $attributeMetadata->setReadOnly((bool) $attribute['read-only']);
                }

                if (isset($attribute['serialized-name'])) {
                    $attributeMetadata->setSerializedName((string) $attribute['serialized-name']);
                }

                if (isset($attribute['type'])) {
                    $attributeMetadata->setType((string) $attribute['type']);
                }
            }

            if (isset($xml['exclusion-policy'])) {
                $classMetadata->setExclusionPolicy((string) $xml['exclusion-policy']);
            }

            if (isset($xml['read-only'])) {
                $classMetadata->setReadOnly((bool) $xml['read-only']);
            }

            if (isset($xml->{'discriminator-map'})) {
                $mapping = array();
                foreach ($xml->{'discriminator-map'}->mapping as $element) {
                    $mapping[(string) $element->attributes()->type] = (string) $element->attributes()->class;
                }

                $classMetadata->setClassDiscriminatorMapping(new ClassDiscriminatorMapping(
                    (string) $xml->{'discriminator-map'}->attributes()->{'type-property'},
                    $mapping
                ));
            }

            return true;
        }

        return false;
    }

    /**
     * Return the names of the classes mapped in this file.
     *
     * @return string[] The classes names
     */
    public function getMappedClasses()
    {
        if (null === $this->classes) {
            $this->classes = $this->getClassesFromXml();
        }

        return array_keys($this->classes);
    }

    /**
     * Parses a XML File.
     *
     * @param string $file Path of file
     *
     * @return \SimpleXMLElement
     *
     * @throws MappingException
     */
    private function parseFile($file)
    {
        try {
            $dom = XmlUtils::loadFile($file, __DIR__.'/schema/dic/serializer-mapping/serializer-mapping-1.0.xsd');
        } catch (\Exception $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }

        return simplexml_import_dom($dom);
    }

    private function getClassesFromXml()
    {
        $xml = $this->parseFile($this->file);
        $classes = array();

        foreach ($xml->class as $class) {
            $classes[(string) $class['name']] = $class;
        }

        return $classes;
    }
}
