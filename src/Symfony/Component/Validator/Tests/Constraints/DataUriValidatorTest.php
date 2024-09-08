<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\DataUri;
use Symfony\Component\Validator\Constraints\DataUriValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Kev <https://github.com/symfonyaml>
 */
class DataUriValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): DataUriValidator
    {
        return new DataUriValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new DataUri());

        $this->assertNoViolation();
    }

    public function testBlankIsValid()
    {
        $this->validator->validate('', new DataUri());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues(string $value)
    {
        $this->validator->validate($value, new DataUri());

        $this->assertNoViolation();
    }

    public static function getValidValues()
    {
        return [
            'mime type is omitted' => ['data:,FooBar'],
            'just charset' => ['data:;charset=UTF-8,FooBar'],
            'plain text' => ['data:text/plain;base64,SGVsbG8sIFdvcmxkIQ=='],
            'text html' => ['data:text/html,%3Ch1%3EHello%2C%20World%21%3C%2Fh1%3E'],
            'plain text with charset' => ['data:text/plain;charset=UTF-8,the%20data:1234,5678'],
            'with meta key=value' => ['data:image/jpeg;key=value;base64,UEsDBBQAAAAI'],
            'without base64 key name' => ['data:image/jpeg;key=value,UEsDBBQAAAAI'],
            'jpeg image' => ['data:image/jpeg;base64,/9j/4AAQSkZJRgABAgAAZABkAAD'],
            'png image' => ['data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg=='],
            'gif image' => ['data:image/gif;base64,R0lGODlhyAAiALM...DfD0QAADs='],
            'svg' => ['data:image/svg+xml,%3Csvg%20version%3D%221.1%22%3E%3C%2Fsvg%3E'],
            'networking applications' => ['data:application/vnd-xxx-query,select_vcount,fcol_from_fieldtable/local,123456789'],
        ];
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $valueAsString)
    {
        $constraint = new DataUri([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $valueAsString)
            ->setCode(DataUri::INVALID_DATA_URI_ERROR)
            ->assertRaised();
    }

    public static function getInvalidValues()
    {
        return [
            'random string' => ['foobar', '"foobar"'],
            'zero' => [0, '"0"'],
            'integer' => [1234, '"1234"'],
            'truncated invalid value' => [
                '1234567890123456789012345678901', // 31 chars
                '"123456789012345678901234567890..." (truncated)',
            ],
        ];
    }
}
