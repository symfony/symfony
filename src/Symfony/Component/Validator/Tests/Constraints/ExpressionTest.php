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
use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class ExpressionTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(ExpressionDummy::class);
        self::assertTrue((new AnnotationLoader())->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame('value == "1"', $aConstraint->expression);
        self::assertSame([], $aConstraint->values);
        self::assertTrue($aConstraint->negate);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame('value == "1"', $bConstraint->expression);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'ExpressionDummy'], $bConstraint->groups);
        self::assertTrue($bConstraint->negate);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame('value == someVariable', $cConstraint->expression);
        self::assertSame(['someVariable' => 42], $cConstraint->values);
        self::assertSame(['foo'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
        self::assertFalse($cConstraint->negate);
    }
}

class ExpressionDummy
{
    #[Expression('value == "1"')]
    private $a;

    #[Expression(expression: 'value == "1"', message: 'myMessage', negate: true)]
    private $b;

    #[Expression(expression: 'value == someVariable', values: ['someVariable' => 42], groups: ['foo'], payload: 'some attached data', negate: false)]
    private $c;
}
