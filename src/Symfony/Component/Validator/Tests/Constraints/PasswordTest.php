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
use Symfony\Component\Validator\Constraints\Password;
use Symfony\Component\Validator\Constraints\PasswordValidator;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * @author Jérémy Reynaud <jeremy@reynaud.io>
 */
class PasswordTest extends TestCase
{
    public function testValidatedByStandardValidator()
    {
        $constraint = new Password();

        static::assertSame(PasswordValidator::class, $constraint->validatedBy());
    }

    public function testDefaultValues()
    {
        $constraint = new Password();

        static::assertSame(12, $constraint->min);
        static::assertFalse($constraint->mixedCase);
        static::assertFalse($constraint->letters);
        static::assertFalse($constraint->numbers);
        static::assertFalse($constraint->symbols);
    }

    public function testAttributes()
    {
        $constraint = new Password();

        $metadata = new ClassMetadata(PasswordDummy::class);
        static::assertTrue((new AnnotationLoader())->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        static::assertSame($constraint->min, $aConstraint->min);
        static::assertFalse($aConstraint->mixedCase);
        static::assertFalse($aConstraint->letters);
        static::assertFalse($aConstraint->numbers);
        static::assertFalse($aConstraint->symbols);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        static::assertSame(8, $bConstraint->min);
        static::assertSame('myMessage', $bConstraint->minMessage);
        static::assertSame(['Default', 'PasswordDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        static::assertTrue($cConstraint->letters);
        static::assertSame('myMessage', $cConstraint->lettersMessage);
        static::assertSame(['my_group'], $cConstraint->groups);
    }
}

class PasswordDummy
{
    #[Password]
    private $a;

    #[Password(min: 8, minMessage: 'myMessage')]
    private $b;

    #[Password(letters: true, lettersMessage: 'myMessage', groups: ['my_group'])]
    private $c;
}
