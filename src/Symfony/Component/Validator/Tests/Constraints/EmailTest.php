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
use Symfony\Component\Validator\Constraints\Email;

class EmailTest extends TestCase
{
    public function testConstructorStrict()
    {
        $subject = new Email(['mode' => Email::VALIDATION_MODE_STRICT]);

        $this->assertEquals(Email::VALIDATION_MODE_STRICT, $subject->mode);
    }

    public function testUnknownModesTriggerException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The "mode" parameter value is not valid.');
        new Email(['mode' => 'Unknown Mode']);
    }

    public function testNormalizerCanBeSet()
    {
        $email = new Email(['normalizer' => 'trim']);

        $this->assertEquals('trim', $email->normalizer);
    }

    public function testInvalidNormalizerThrowsException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new Email(['normalizer' => 'Unknown Callable']);
    }

    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new Email(['normalizer' => new \stdClass()]);
    }
}
