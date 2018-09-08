<?php

declare(strict_types=1);

namespace Symfony\Component\Serializer\Tests\Context;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Context\AccumulatingContext;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

final class AccumulatingContextTest extends TestCase
{
    /**
     * @var AccumulatingContext
     */
    private $accumulatingContext;

    protected function setUp(): void
    {
        $this->accumulatingContext = new AccumulatingContext();
    }

    /**
     * @dataProvider dataToSerializeProvider
     */
    public function testFlatten(array $expectedFlattenedData, array $data): void
    {
        foreach ($data as $key => $value) {
            $this->accumulatingContext[$key] = $value;
        }

        $this->assertEquals($expectedFlattenedData, $this->accumulatingContext->flatten());
    }

    public function dataToSerializeProvider(): array
    {
        return array(
            array(
                array(), array(),
            ),
            array(
                array('foo' => array(new UnexpectedValueException('test'))), array('foo' => array(new UnexpectedValueException('test'))),
            ),
            array(
                array('foo.bar' => array(new UnexpectedValueException('test'))), array('foo' => array('bar' => array(new UnexpectedValueException('test')))),
            ),
            array(
                array(
                    'foo.bar.baz' => array(new UnexpectedValueException('test')),
                    'foo.bar.quux' => array(new UnexpectedValueException('test2')),
                    'foo.corge.grault' => array(new UnexpectedValueException('test3')),
                    'garply' => array(new UnexpectedValueException('test4')),
                ),
                array(
                    'foo' => array(
                        'bar' => array(
                            'baz' => array(new UnexpectedValueException('test')),
                            'quux' => array(new UnexpectedValueException('test2')),
                        ),
                        'corge' => array(
                            'grault' => array(new UnexpectedValueException('test3')),
                        ),
                    ),
                    'garply' => array(new UnexpectedValueException('test4')),
                ),
            ),
        );
    }
}
