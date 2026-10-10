<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\Metadata\EnumMappingMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReverseClassObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\StatusLabelTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping\StatusSource;
use Symfony\Component\ObjectMapper\Transform\MapEnum;

class EnumMappingMetadataFactoryTest extends TestCase
{
    public function testEnumConversionKeepsTheOtherArgumentsOfTheMapping()
    {
        $factory = new EnumMappingMetadataFactory(new ReverseClassObjectMapperMetadataFactory(new ReflectionObjectMapperMetadataFactory(), [StatusSource::class => [StatusLabelTarget::class]]));

        $mappings = $factory->create(new StatusSource(), 'status', ['source' => StatusSource::class, 'target' => StatusLabelTarget::class]);

        $this->assertCount(1, $mappings);
        $this->assertSame('label', $mappings[0]->target);
        $this->assertSame('status', $mappings[0]->source);
        $this->assertSame(StatusLabelTarget::class, $mappings[0]->targetClass);
        $this->assertInstanceOf(MapEnum::class, $mappings[0]->transform);
    }
}
