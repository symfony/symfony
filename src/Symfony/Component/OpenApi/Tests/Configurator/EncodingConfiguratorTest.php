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
use Symfony\Component\OpenApi\Configurator\ParameterConfigurator;
use Symfony\Component\OpenApi\Model\Encoding;
use Symfony\Component\OpenApi\Model\Parameter;

class EncodingConfiguratorTest extends AbstractConfiguratorTestCase
{
    public function testBuildEmpty(): void
    {
        $encoding = (new EncodingConfigurator())->build();
        $this->assertInstanceOf(Encoding::class, $encoding);
        $this->assertNull($encoding->getContentType());
        $this->assertNull($encoding->getStyle());
        $this->assertNull($encoding->isExplode());
        $this->assertNull($encoding->allowReserved());
        $this->assertEmpty($encoding->getHeaders());
        $this->assertSame([], $encoding->getSpecificationExtensions());
    }

    public function testBuildFull(): void
    {
        [$headerConfigurator, $header] = $this->createConfiguratorMock(ParameterConfigurator::class, Parameter::class);

        $configurator = (new EncodingConfigurator())
            ->contentType('application/json')
            ->style('form')
            ->explode(true)
            ->allowReserved(false)
            ->header('Content-Type', $headerConfigurator)
            ->specificationExtension('x-ext', 'value')
        ;

        $encoding = $configurator->build();
        $this->assertInstanceOf(Encoding::class, $encoding);
        $this->assertSame('application/json', $encoding->getContentType());
        $this->assertSame('form', $encoding->getStyle());
        $this->assertTrue($encoding->isExplode());
        $this->assertFalse($encoding->allowReserved());
        $this->assertArrayHasKey('Content-Type', $encoding->getHeaders());
        $this->assertSame($header, $encoding->getHeaders()['Content-Type']);
        $this->assertSame(['x-ext' => 'value'], $encoding->getSpecificationExtensions());
    }
}
