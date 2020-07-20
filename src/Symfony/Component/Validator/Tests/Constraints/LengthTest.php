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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Validator\Constraints\Length;

/**
 * @author Renan Taranto <renantaranto@gmail.com>
 */
class LengthTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testNormalizerCanBeSet()
    {
        $length = new Length(['min' => 0, 'max' => 10, 'normalizer' => 'trim']);

        $this->assertEquals('trim', $length->normalizer);
    }

    public function testInvalidNormalizerThrowsException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new Length(['min' => 0, 'max' => 10, 'normalizer' => 'Unknown Callable']);
    }

    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new Length(['min' => 0, 'max' => 10, 'normalizer' => new \stdClass()]);
    }

    /**
     * @group legacy
     * @dataProvider allowEmptyStringOptionData
     */
    public function testDeprecatedAllowEmptyStringOption(bool $value)
    {
        $this->expectDeprecation('Since symfony/validator 5.2: The "allowEmptyString" option of the "Symfony\Component\Validator\Constraints\Length" constraint is deprecated.');

        new Length(['allowEmptyString' => $value, 'max' => 5]);
    }

    public function allowEmptyStringOptionData()
    {
        return [
            [true],
            [false],
        ];
    }
}
