<?php

declare(strict_types=1);

namespace Symfony\Component\Serializer\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValuesException;

final class UnexpectedValuesExceptionTest extends TestCase
{
    /**
     * @dataProvider wrongErrorsProvider
     */
    public function testItCannotBeInstantiatedWithWrongErrors(string $expectedExceptionMessage, array $errors): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        new UnexpectedValuesException($errors);
    }

    public function testItCanBeInstantiated(): void
    {
        new UnexpectedValuesException([
            'attribute1' => [new UnexpectedValueException('foo')],
            'attribute2' => [new UnexpectedValueException('bar')],
            'attribute3.subattibute1' => [new UnexpectedValueException('bar')],
        ]);
    }

    public function wrongErrorsProvider()
    {
        return [
            [
                'No errors were given, at least one is expected.', [],
            ],
            [
                'All keys must be strings, integer given.', [0 => []],
            ],
            [
                'No errors were given for key "attribute1", at least one is expected.', ['attribute1' => []],
            ],
            [
                'All errors must be instances of '.UnexpectedValueException::class.', integer given for key "attribute1".', ['attribute1' => [123]],
            ],
            [
                'All errors must be instances of '.UnexpectedValueException::class.', string given for key "attribute2".', ['attribute2' => ['foo']],
            ],
            [
                'All errors must be instances of '.UnexpectedValueException::class.', RuntimeException given for key "attribute3".', ['attribute3' => [new \RuntimeException('test')]],
            ],
            [
                'All errors must be instances of '.UnexpectedValueException::class.', RuntimeException given for key "attribute4.subattribute1".', ['attribute4.subattribute1' => [new \RuntimeException('test')]],
            ],
        ];
    }
}
