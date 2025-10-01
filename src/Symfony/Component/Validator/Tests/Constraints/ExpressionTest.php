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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class ExpressionTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(ExpressionDummy::class);
        self::assertTrue((new AttributeLoader())->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        self::assertSame('value == "1"', $aConstraint->expression);
        self::assertSame([], $aConstraint->values);
        self::assertTrue($aConstraint->negate);

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertSame('value == "1"', $bConstraint->expression);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'ExpressionDummy'], $bConstraint->groups);
        self::assertTrue($bConstraint->negate);

        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        self::assertSame('value == someVariable', $cConstraint->expression);
        self::assertSame(['someVariable' => 42], $cConstraint->values);
        self::assertSame(['foo'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
        self::assertFalse($cConstraint->negate);
    }

    public function testMissingPattern()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The options "expression" must be set for constraint "%s".', Expression::class));

        new Expression(null);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testMissingPatternDoctrineStyle()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The options "expression" must be set for constraint "%s".', Expression::class));

        new Expression([]);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testInitializeWithOptionsArray()
    {
        $constraint = new Expression([
            'expression' => '!this.getParent().get("field2").getData()',
        ]);

        $this->assertSame('!this.getParent().get("field2").getData()', $constraint->expression);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testExpressionInOptionsArray()
    {
        $constraint = new Expression(null, options: ['expression' => '!this.getParent().get("field2").getData()']);

        $this->assertSame('!this.getParent().get("field2").getData()', $constraint->expression);
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
