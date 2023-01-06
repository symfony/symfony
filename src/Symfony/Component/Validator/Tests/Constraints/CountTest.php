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
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class CountTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(CountDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame(42, $aConstraint->min);
        self::assertSame(42, $aConstraint->max);
        self::assertNull($aConstraint->divisibleBy);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(1, $bConstraint->min);
        self::assertSame(4711, $bConstraint->max);
        self::assertNull($bConstraint->divisibleBy);
        self::assertSame('myMinMessage', $bConstraint->minMessage);
        self::assertSame('myMaxMessage', $bConstraint->maxMessage);
        self::assertSame(['Default', 'CountDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertNull($cConstraint->min);
        self::assertNull($cConstraint->max);
        self::assertSame(10, $cConstraint->divisibleBy);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class CountDummy
{
    #[Count(exactly: 42)]
    private $a;

    #[Count(min: 1, max: 4711, minMessage: 'myMinMessage', maxMessage: 'myMaxMessage')]
    private $b;

    #[Count(divisibleBy: 10, groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
