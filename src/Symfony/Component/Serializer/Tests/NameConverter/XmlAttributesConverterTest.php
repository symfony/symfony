<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\NameConverter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\XmlAttributesConverter;

class XmlAttributesConverterTest extends TestCase
{
    /**
     * @dataProvider normalizeDataProvider
     */
    public function testNormalize(?string $attributePrefix, ?string $nodeValueAttributeName, string $propertyName, string $expectedPropertyName): void
    {
        $xmlAttributeConverter = $this->createXmlAttributesConverter($attributePrefix, $nodeValueAttributeName);
        $result = $xmlAttributeConverter->normalize($propertyName);
        $this->assertEquals($expectedPropertyName, $result);
    }

    /**
     * @dataProvider denormalizeDataProvider
     */
    public function testDenormalize(?string $attributePrefix, ?string $nodeValueAttributeName, string $propertyName, string $expectedPropertyName): void
    {
        $xmlAttributeConverter = $this->createXmlAttributesConverter($attributePrefix, $nodeValueAttributeName);
        $result = $xmlAttributeConverter->denormalize($propertyName);
        $this->assertEquals($expectedPropertyName, $result);
    }

    public function normalizeDataProvider()
    {
        return [
            'defaults to attr extra attribute' => [null, null, 'attrOwnerID', '@OwnerID'],
            'no extra attributes' => [null, null, 'someOtherParam', 'someOtherParam'],
            'node value' => [null, null, 'value', '#'],
            'custom extra attribute prefix' => ['someAttributePrefix', 'nodeValue', 'someAttributePrefixOwnerID', '@OwnerID'],
            'custom node value attribute' => ['someAttributePrefix', 'nodeValue', 'nodeValue', '#'],
        ];
    }

    public function denormalizeDataProvider()
    {
        return [
            'defaults to attr extra attribute' => [null, null, '@OwnerID', 'attrOwnerID'],
            'no extra attributes' => [null, null, 'SomeOtherParam', 'SomeOtherParam'],
            'no extra attributes lowercase' => [null, null, 'someOtherParam', 'someOtherParam'],
            'node value' => [null, null, '#', 'value'],
            'custom extra attribute prefix' => ['someAttributePrefix', 'nodeValue', '@OwnerID', 'someAttributePrefixOwnerID'],
            'custom node value attribute' => ['someAttributePrefix', 'nodeValue', '#', 'nodeValue'],
        ];
    }

    private function createXmlAttributesConverter(?string $attributePrefix, ?string $nodeValueAttributeName): XmlAttributesConverter
    {
        if (null === $attributePrefix && null === $nodeValueAttributeName) {
            return new XmlAttributesConverter();
        }

        return new XmlAttributesConverter($attributePrefix, $nodeValueAttributeName);
    }
}
