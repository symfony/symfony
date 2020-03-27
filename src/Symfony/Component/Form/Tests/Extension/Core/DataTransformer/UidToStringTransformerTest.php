<?php


namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\UidToStringTransformer;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;

class UidToStringTransformerTest extends TestCase
{
    /**
     * @var UidToStringTransformer
     */
    private $transformer;

    protected function setUp(): void
    {
        $this->transformer = new UidToStringTransformer();
    }

    public function dataProvider()
    {
        return [
            ['9b7541de-6f87-11ea-ab3c-9da9a81562fc', UuidV1::class],
            ['e576629b-ff34-3642-9c08-1f5219f0d45b', UuidV3::class],
            ['4126dbc1-488e-4f6e-aadd-775dcbac482e', UuidV4::class],
            ['18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22', UuidV5::class],
            ['1ea6ecef-eb9a-66fe-b62b-957b45f17e43', UuidV6::class],
            ['01E4BYF64YZ97MDV6RH0HAMN6X', Ulid::class],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testTransform($uidString, $class)
    {
        $this->assertEquals($uidString, $this->transformer->transform($class::fromString($uidString)));
    }

    public function testTransformForNonUid()
    {
        $this->assertEquals('', $this->transformer->transform(null));

        $this->expectException(TransformationFailedException::class);
        $this->transformer->transform(new \stdClass());
    }

    /**
     * @dataProvider dataProvider
     */
    public function testReverseTransform($uidString, $class)
    {
        $this->assertEquals($class::fromString($uidString), $this->transformer->reverseTransform($uidString));
    }

    public function testReverseTransformForInvalidValue()
    {
        $this->assertNull($this->transformer->reverseTransform(''));

        $this->expectException(TransformationFailedException::class);
        $this->transformer->reverseTransform('foo');
    }
}
