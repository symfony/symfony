<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Mapping\Loader;

use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\PropertyAccess\Exception\MappingException;
use Symfony\Component\PropertyAccess\Mapping\PropertyMetadata;
use Symfony\Component\PropertyAccess\Mapping\ClassMetadata;

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
    public function loadClassMetadata(ClassMetadata $classMetadata)
    {
        if (null === $this->classes) {
            $this->classes = array();
            $xml = $this->parseFile($this->file);

            foreach ($xml->class as $class) {
                $this->classes[(string) $class['name']] = $class;
            }
        }

        $attributesMetadata = $classMetadata->getPropertyMetadataCollection();

        if (isset($this->classes[$classMetadata->getName()])) {
            $xml = $this->classes[$classMetadata->getName()];

            foreach ($xml->property as $attribute) {
                $attributeName = (string) $attribute['name'];

                if (isset($attributesMetadata[$attributeName])) {
                    $attributeMetadata = $attributesMetadata[$attributeName];
                } else {
                    $attributeMetadata = new PropertyMetadata($attributeName);
                    $classMetadata->addPropertyMetadata($attributeMetadata);
                }

                if (isset($attribute['getter'])) {
                    $attributeMetadata->setGetter($attribute['getter']);
                }

                if (isset($attribute['setter'])) {
                    $attributeMetadata->setSetter($attribute['setter']);
                }

                if (isset($attribute['adder'])) {
                    $attributeMetadata->setAdder($attribute['adder']);
                }

                if (isset($attribute['remover'])) {
                    $attributeMetadata->setRemover($attribute['remover']);
                }
            }

            return true;
        }

        return false;
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
            $dom = XmlUtils::loadFile($file, __DIR__.'/schema/dic/property-access-mapping/property-access-mapping-1.0.xsd');
        } catch (\Exception $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }

        return simplexml_import_dom($dom);
    }
}
