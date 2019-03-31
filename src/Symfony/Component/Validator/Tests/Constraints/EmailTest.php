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
    /**
     * @expectedDeprecation The "strict" property is deprecated since Symfony 4.1. Use "mode"=>"strict" instead.
     * @group legacy
     */
    public function testLegacyConstructorStrict()
    {
        $subject = new Email(['strict' => true]);

        $this->assertTrue($subject->strict);
    }

    public function testConstructorStrict()
    {
        $subject = new Email(['mode' => Email::VALIDATION_MODE_STRICT]);

        $this->assertEquals(Email::VALIDATION_MODE_STRICT, $subject->mode);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "mode" parameter value is not valid.
     */
    public function testUnknownModesTriggerException()
    {
        new Email(['mode' => 'Unknown Mode']);
    }

    public function testNormalizerCanBeSet()
    {
        $email = new Email(['normalizer' => 'trim']);

        $this->assertEquals('trim', $email->normalizer);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "normalizer" option must be a valid callable ("string" given).
     */
    public function testInvalidNormalizerThrowsException()
    {
        new Email(['normalizer' => 'Unknown Callable']);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "normalizer" option must be a valid callable ("stdClass" given).
     */
    public function testInvalidNormalizerObjectThrowsException()
    {
        new Email(['normalizer' => new \stdClass()]);
    }
}
