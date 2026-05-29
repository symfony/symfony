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
use Symfony\Component\Validator\Constraints\PhoneNumber;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

/**
 * @author Joppe De Cuyper <hello@joppe.dev>
 */
class PhoneNumberTest extends TestCase
{
    public function testNormalizerCanBeSet()
    {
        $constraint = new PhoneNumber(normalizer: 'trim');

        $this->assertEquals('trim', $constraint->normalizer);
    }

    public function testCanBeSerializedWithStringNormalizer()
    {
        $constraint = unserialize(serialize(new PhoneNumber(normalizer: 'trim')));

        $this->assertSame('trim', $constraint->normalizer);
    }

    public function testModeDefaultsToNull()
    {
        $constraint = new PhoneNumber();

        $this->assertNull($constraint->mode);
    }

    public function testModeCanBeSet()
    {
        $constraint = new PhoneNumber(mode: PhoneNumber::MODE_STRICT);

        $this->assertSame(PhoneNumber::MODE_STRICT, $constraint->mode);
    }

    public function testInvalidModeThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "mode" parameter value is not valid.');

        new PhoneNumber(mode: 'invalid');
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(PhoneNumberDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        self::assertSame('This value is not a valid phone number.', $aConstraint->message);
        self::assertNull($aConstraint->normalizer);
        self::assertNull($aConstraint->mode);
        self::assertSame(['Default', 'PhoneNumberDummy'], $aConstraint->groups);

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame('trim', $bConstraint->normalizer);
        self::assertSame(['Default', 'PhoneNumberDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);

        [$dConstraint] = $metadata->getPropertyMetadata('d')[0]->getConstraints();
        self::assertSame(PhoneNumber::MODE_STRICT, $dConstraint->mode);
    }
}

class PhoneNumberDummy
{
    #[PhoneNumber]
    private $a;

    #[PhoneNumber(message: 'myMessage', normalizer: 'trim')]
    private $b;

    #[PhoneNumber(groups: ['my_group'], payload: 'some attached data')]
    private $c;

    #[PhoneNumber(mode: PhoneNumber::MODE_STRICT)]
    private $d;
}
