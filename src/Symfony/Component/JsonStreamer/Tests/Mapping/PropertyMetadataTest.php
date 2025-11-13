<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Mapping;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadata;
use Symfony\Component\TypeInfo\Type;

class PropertyMetadataTest extends TestCase
{
    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testStreamToNativeValueTransformersDeprecations()
    {
        $this->expectUserDeprecationMessage('Since symfony/json-streamer 7.4: The "streamToNativeValueTransformers" parameter of the "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::__construct()" method is deprecated. Use "valueTransformers" instead.');
        $propertyMetadata = new PropertyMetadata('name', Type::bool(), ['strtoupper'], ['strtolower']);

        $this->expectUserDeprecationMessage('Since symfony/json-streamer 7.4: The "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::getNativeToStreamValueTransformer()" method is deprecated, use "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::getValueTransformers()" instead.');
        $propertyMetadata->getNativeToStreamValueTransformer();

        $this->expectUserDeprecationMessage('Since symfony/json-streamer 7.4: The "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::getStreamToNativeValueTransformers()" method is deprecated, use "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::getValueTransformers()" instead.');
        $propertyMetadata->getStreamToNativeValueTransformers();

        $this->expectUserDeprecationMessage('Since symfony/json-streamer 7.4: The "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::withNativeToStreamValueTransformers()" method is deprecated, use "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::withValueTransformers()" instead.');
        $propertyMetadata->withNativeToStreamValueTransformers([]);

        $this->expectUserDeprecationMessage('Since symfony/json-streamer 7.4: The "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::withStreamToNativeValueTransformers()" method is deprecated, use "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::withValueTransformers()" instead.');
        $propertyMetadata->withStreamToNativeValueTransformers([]);

        $this->expectUserDeprecationMessage('Since symfony/json-streamer 7.4: The "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::withAdditionalNativeToStreamValueTransformer()" method is deprecated, use "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::withAdditionalValueTransformer()" instead.');
        $propertyMetadata->withAdditionalNativeToStreamValueTransformer('strtoupper');

        $this->expectUserDeprecationMessage('Since symfony/json-streamer 7.4: The "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::withAdditionalStreamToNativeValueTransformer()" method is deprecated, use "Symfony\Component\JsonStreamer\Mapping\PropertyMetadata::withAdditionalValueTransformer()" instead.');
        $propertyMetadata->withAdditionalStreamToNativeValueTransformer('strtolower');
    }
}
