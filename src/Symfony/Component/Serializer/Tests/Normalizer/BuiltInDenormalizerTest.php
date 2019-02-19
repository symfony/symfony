<?php


namespace Symfony\Component\Serializer\Tests\Normalizer;


use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\BuiltInDenormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class BuiltInDenormalizerTest extends TestCase
{
    /**
     * @var BuiltInDenormalizer
     */
    private $denormalizer;

    protected function setUp()
    {
        $this->denormalizer = new BuiltInDenormalizer();
    }

    /**
     * @dataProvider basicTypes
     */
    public function testDenormalize($value, $type)
    {
        $this->assertTrue($this->denormalizer->supportsDenormalization($value, $type));

        $result = $this->denormalizer->denormalize($value, $type);
        $this->assertEquals($value, $result);
    }

    public function basicTypes()
    {
        return [
            [1, 'int'],
            [1.1, 'float'],
            ['hello', 'string'],
            [true, 'bool'],
            [false, 'bool'],
            [[], 'array']
        ];
    }

    /**
     * @dataProvider basicTypesWrong
     * @expectedException \Symfony\Component\Serializer\Exception\NotNormalizableValueException
     */
    public function testFailDenormalize($value, $type)
    {
        $result = $this->denormalizer->denormalize($value, $type);
        $this->assertEquals($value, $result);
    }

    public function basicTypesWrong()
    {
        return [
            [1.1, 'int'],
            [1, 'float'],
            [1, 'string'],
            [1, 'bool'],
            ['hello', 'array']
        ];
    }

    /**
     * @dataProvider castTypes
     */
    public function testCastDenormalize($value, $type, $corrected, $format)
    {
        $result = $this->denormalizer->denormalize($value, $type, $format);
        $this->assertEquals($corrected, $result);
    }

    public function castTypes()
    {
        return [
            ['1', 'int', 1, 'xml'],
            ['1.1', 'float', 1.1, 'xml'],
            ['1', 'bool', true, 'xml'],
            ['0', 'bool', false, 'xml'],
            ['1', 'int', 1, 'csv'],
            ['1.1', 'float', 1.1, 'csv'],
            ['1', 'bool', true, 'csv'],
            ['0', 'bool', false, 'csv'],
            [1, 'float', 1.0, 'json']
        ];
    }
}
