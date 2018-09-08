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
        new UnexpectedValuesException(array(
            'attribute1' => array(new UnexpectedValueException('foo')),
            'attribute2' => array(new UnexpectedValueException('bar')),
            'attribute3.subattibute1' => array(new UnexpectedValueException('bar')),
        ));
    }

    public function wrongErrorsProvider()
    {
        return array(
            array(
                'No errors were given, at least one is expected.', array(),
            ),
            array(
                'All keys must be strings, integer given.', array(0 => array()),
            ),
            array(
                'No errors were given for key "attribute1", at least one is expected.', array('attribute1' => array()),
            ),
            array(
                'All errors must be instances of '.UnexpectedValueException::class.', integer given for key "attribute1".', array('attribute1' => array(123)),
            ),
            array(
                'All errors must be instances of '.UnexpectedValueException::class.', string given for key "attribute2".', array('attribute2' => array('foo')),
            ),
            array(
                'All errors must be instances of '.UnexpectedValueException::class.', RuntimeException given for key "attribute3".', array('attribute3' => array(new \RuntimeException('test'))),
            ),
            array(
                'All errors must be instances of '.UnexpectedValueException::class.', RuntimeException given for key "attribute4.subattribute1".', array('attribute4.subattribute1' => array(new \RuntimeException('test'))),
            ),
        );
    }
}
