<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Tests\Configurator;

use Symfony\Component\OpenApi\Configurator\EncodingConfigurator;
use Symfony\Component\OpenApi\Configurator\ExampleConfigurator;
use Symfony\Component\OpenApi\Configurator\MediaTypeConfigurator;
use Symfony\Component\OpenApi\Configurator\SchemaConfigurator;
use Symfony\Component\OpenApi\Model\Encoding;
use Symfony\Component\OpenApi\Model\Example;
use Symfony\Component\OpenApi\Model\MediaType;
use Symfony\Component\OpenApi\Model\Schema;

class MediaTypeConfiguratorTest extends AbstractConfiguratorTestCase
{
    public function testBuildEmpty(): void
    {
        $configurator = new MediaTypeConfigurator();

        $mediaType = $configurator->build();
        $this->assertInstanceOf(MediaType::class, $mediaType);
        $this->assertNull($mediaType->getSchema());
        $this->assertNull($mediaType->getExample());
        $this->assertNull($mediaType->getExamples());
        $this->assertNull($mediaType->getEncodings());
        $this->assertSame([], $mediaType->getSpecificationExtensions());
    }

    public function testBuildFull(): void
    {
        [$schemaConfigurator, $schema] = $this->createConfiguratorMock(SchemaConfigurator::class, Schema::class);
        [$exampleConfigurator, $example] = $this->createConfiguratorMock(ExampleConfigurator::class, Example::class);
        [$encodingConfigurator, $encoding] = $this->createConfiguratorMock(EncodingConfigurator::class, Encoding::class);

        $configurator = (new MediaTypeConfigurator())
            ->schema($schemaConfigurator)
            ->example('example')
            ->example('ExampleName', $exampleConfigurator)
            ->encoding('EncodingName', $encodingConfigurator)
            ->specificationExtension('x-ext', 'value')
        ;

        $mediaType = $configurator->build();
        $this->assertInstanceOf(MediaType::class, $mediaType);
        $this->assertSame($schema, $mediaType->getSchema());
        $this->assertSame('example', $mediaType->getExample());
        $this->assertSame($example, $mediaType->getExamples()['ExampleName']);
        $this->assertSame($encoding, $mediaType->getEncodings()['EncodingName']);
        $this->assertSame(['x-ext' => 'value'], $mediaType->getSpecificationExtensions());
    }
}
