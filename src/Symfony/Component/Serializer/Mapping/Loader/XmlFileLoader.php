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

                if (isset($attribute['max-depth'])) {
                    $attributeMetadata->setMaxDepth((int) $attribute['max-depth']);
                }

                if (isset($attribute['serialized-name'])) {
                    $attributeMetadata->setSerializedName((string) $attribute['serialized-name']);
                }

                if (isset($attribute['ignore'])) {
                    $attributeMetadata->setIgnore(XmlUtils::phpize($attribute['ignore']));
                }

                foreach ($attribute->context as $node) {
                    $groups = (array) $node->group;
                    $context = $this->parseContext($node->entry);
                    $attributeMetadata->setNormalizationContextForGroups($context, $groups);
                    $attributeMetadata->setDenormalizationContextForGroups($context, $groups);
                }

                foreach ($attribute->normalization_context as $node) {
                    $groups = (array) $node->group;
                    $context = $this->parseContext($node->entry);
                    $attributeMetadata->setNormalizationContextForGroups($context, $groups);
                }

                foreach ($attribute->denormalization_context as $node) {
                    $groups = (array) $node->group;
                    $context = $this->parseContext($node->entry);
                    $attributeMetadata->setDenormalizationContextForGroups($context, $groups);
                }
            }

            if (isset($xml->{'discriminator-map'})) {
                $mapping = [];
                foreach ($xml->{'discriminator-map'}->mapping as $element) {
                    $elementAttributes = $element->attributes();
                    $mapping[(string) $elementAttributes->type] = (string) $elementAttributes->class;
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
     * @return string[]
     */
    public function getMappedClasses()
    {
        if (null === $this->classes) {
            $this->classes = $this->getClassesFromXml();
        }

        return array_keys($this->classes);
    }

    /**
     * Parses an XML File.
     *
     * @throws MappingException
     */
    private function parseFile(string $file): \SimpleXMLElement
    {
        try {
            $dom = XmlUtils::loadFile($file, __DIR__.'/schema/dic/serializer-mapping/serializer-mapping-1.0.xsd');
        } catch (\Exception $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }

        return simplexml_import_dom($dom);
    }

    private function getClassesFromXml(): array
    {
        $xml = $this->parseFile($this->file);
        $classes = [];

        foreach ($xml->class as $class) {
            $classes[(string) $class['name']] = $class;
        }

        return $classes;
    }

    private function parseContext(\SimpleXMLElement $nodes): array
    {
        $context = [];

        foreach ($nodes as $node) {
            if (\count($node) > 0) {
                if (\count($node->entry) > 0) {
                    $value = $this->parseContext($node->entry);
                } else {
                    $value = [];
                }
            } else {
                $value = XmlUtils::phpize($node);
            }

            if (isset($node['name'])) {
                $context[(string) $node['name']] = $value;
            } else {
                $context[] = $value;
            }
        }

        return $context;
    }
}
