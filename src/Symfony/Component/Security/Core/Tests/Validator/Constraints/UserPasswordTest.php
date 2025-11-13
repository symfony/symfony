<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class UserPasswordTest extends TestCase
{
    public function testValidatedByStandardValidator()
    {
        $constraint = new UserPassword();

        self::assertSame('security.validator.user_password', $constraint->validatedBy());
    }

    #[DataProvider('provideServiceValidatedConstraints')]
    public function testValidatedByService(UserPassword $constraint)
    {
        self::assertSame('my_service', $constraint->validatedBy());
    }

    public static function provideServiceValidatedConstraints(): iterable
    {
        yield 'named arguments' => [new UserPassword(service: 'my_service')];

        $metadata = new ClassMetadata(UserPasswordDummy::class);
        self::assertTrue((new AttributeLoader())->loadClassMetadata($metadata));

        yield 'attribute' => [$metadata->getPropertyMetadata('b')[0]->getConstraints()[0]];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testValidatedByServiceDoctrineStyle()
    {
        self::assertSame('my_service', (new UserPassword(['service' => 'my_service']))->validatedBy());
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(UserPasswordDummy::class);
        self::assertTrue((new AttributeLoader())->loadClassMetadata($metadata));

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'UserPasswordDummy'], $bConstraint->groups);
        self::assertNull($bConstraint->payload);

        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class UserPasswordDummy
{
    #[UserPassword]
    private $a;

    #[UserPassword(service: 'my_service', message: 'myMessage')]
    private $b;

    #[UserPassword(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
