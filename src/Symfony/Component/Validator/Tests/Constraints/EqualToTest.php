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
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class EqualToTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(EqualToDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame(2, $aConstraint->value);
        self::assertNull($aConstraint->propertyPath);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(4711, $bConstraint->value);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'EqualToDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertNull($cConstraint->value);
        self::assertSame('b', $cConstraint->propertyPath);
        self::assertSame('myMessage', $cConstraint->message);
        self::assertSame(['foo'], $cConstraint->groups);
    }
}

class EqualToDummy
{
    #[EqualTo(2)]
    private $a;

    #[EqualTo(value: 4711, message: 'myMessage')]
    private $b;

    #[EqualTo(propertyPath: 'b', message: 'myMessage', groups: ['foo'])]
    private $c;
}
