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

use Symfony\Component\OpenApi\Configurator\ReferenceConfigurator;
use Symfony\Component\OpenApi\Model\Reference;

class ReferenceConfiguratorTest extends AbstractConfiguratorTestCase
{
    public function provideNormalize(): iterable
    {
        yield ['ReferenceName', 'ReferenceName'];
        yield ['App\\Transformer\\ExampleTransformer', 'App_Transformer_ExampleTransformer'];
        yield ['file.php', 'file.php'];
        yield ['invalid-name', 'invalid-name'];
        yield ['invalid_name', 'invalid_name'];
        yield ['invalid||name', 'invalid_name'];
    }

    /**
     * @dataProvider provideNormalize
     */
    public function testNormalize(string $input, string $expectedOutput): void
    {
        $this->assertSame($expectedOutput, ReferenceConfigurator::normalize($input));
    }

    public function testBuildEmpty(): void
    {
        $configurator = new ReferenceConfigurator('#/components/schemas/ReferenceName');

        $reference = $configurator->build();
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertSame('#/components/schemas/ReferenceName', $reference->getRef());
        $this->assertNull($reference->getDescription());
        $this->assertNull($reference->getSummary());
        $this->assertSame([], $reference->getSpecificationExtensions());
    }

    public function testBuildFull(): void
    {
        $configurator = (new ReferenceConfigurator('#/components/schemas/ReferenceName'))
            ->description('description')
            ->summary('summary')
            ->specificationExtension('x-ext', 'value')
        ;

        $reference = $configurator->build();
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertSame('#/components/schemas/ReferenceName', $reference->getRef());
        $this->assertSame('description', $reference->getDescription());
        $this->assertSame('summary', $reference->getSummary());
        $this->assertSame(['x-ext' => 'value'], $reference->getSpecificationExtensions());
    }
}
