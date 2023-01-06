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
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class EmailTest extends TestCase
{
    public function testConstructorStrict()
    {
        $subject = new Email(['mode' => Email::VALIDATION_MODE_STRICT]);

        $this->assertEquals(Email::VALIDATION_MODE_STRICT, $subject->mode);
    }

    public function testConstructorHtml5AllowNoTld()
    {
        $subject = new Email(['mode' => Email::VALIDATION_MODE_HTML5_ALLOW_NO_TLD]);

        $this->assertEquals(Email::VALIDATION_MODE_HTML5_ALLOW_NO_TLD, $subject->mode);
    }

    public function testUnknownModesTriggerException()
    {
        $this->expectException(InvalidArgumentException::class);
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
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new Email(['normalizer' => 'Unknown Callable']);
    }

    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new Email(['normalizer' => new \stdClass()]);
    }

    public function testAttribute()
    {
        $metadata = new ClassMetadata(EmailDummy::class);
        (new AnnotationLoader())->loadClassMetadata($metadata);

        [$aConstraint] = $metadata->properties['a']->constraints;
        self::assertNull($aConstraint->mode);
        self::assertNull($aConstraint->normalizer);

        [$bConstraint] = $metadata->properties['b']->constraints;
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(Email::VALIDATION_MODE_HTML5, $bConstraint->mode);
        self::assertSame('trim', $bConstraint->normalizer);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class EmailDummy
{
    #[Email]
    private $a;

    #[Email(message: 'myMessage', mode: Email::VALIDATION_MODE_HTML5, normalizer: 'trim')]
    private $b;

    #[Email(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
