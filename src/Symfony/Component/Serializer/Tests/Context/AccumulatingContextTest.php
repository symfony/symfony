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
        return [
            [
                [], [],
            ],
            [
                ['foo' => [new UnexpectedValueException('test')]], ['foo' => [new UnexpectedValueException('test')]],
            ],
            [
                ['foo.bar' => [new UnexpectedValueException('test')]], ['foo' => ['bar' => [new UnexpectedValueException('test')]]],
            ],
            [
                [
                    'foo.bar.baz' => [new UnexpectedValueException('test')],
                    'foo.bar.quux' => [new UnexpectedValueException('test2')],
                    'foo.corge.grault' => [new UnexpectedValueException('test3')],
                    'garply' => [new UnexpectedValueException('test4')],
                ],
                [
                    'foo' => [
                        'bar' => [
                            'baz' => [new UnexpectedValueException('test')],
                            'quux' => [new UnexpectedValueException('test2')],
                        ],
                        'corge' => [
                            'grault' => [new UnexpectedValueException('test3')],
                        ]
                    ],
                    'garply' => [new UnexpectedValueException('test4')],
                ],
            ]
        ];
    }
}
