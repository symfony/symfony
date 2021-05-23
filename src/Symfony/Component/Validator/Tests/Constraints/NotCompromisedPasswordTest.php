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
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class NotCompromisedPasswordTest extends TestCase
{
    public function testDefaultValues()
    {
        $constraint = new NotCompromisedPassword();
        $this->assertSame(1, $constraint->threshold);
        $this->assertFalse($constraint->skipOnError);
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(NotCompromisedPasswordDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame(1, $aConstraint->threshold);
        self::assertFalse($aConstraint->skipOnError);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(42, $bConstraint->threshold);
        self::assertTrue($bConstraint->skipOnError);
        self::assertSame(['Default', 'NotCompromisedPasswordDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class NotCompromisedPasswordDummy
{
    #[NotCompromisedPassword]
    private $a;

    #[NotCompromisedPassword(message: 'myMessage', threshold: 42, skipOnError: true)]
    private $b;

    #[NotCompromisedPassword(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
