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
use Symfony\Component\Validator\Constraints\ExpressionSyntax;
use Symfony\Component\Validator\Constraints\ExpressionSyntaxValidator;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class ExpressionSyntaxTest extends TestCase
{
    public function testValidatedByStandardValidator()
    {
        $constraint = new ExpressionSyntax();

        self::assertSame(ExpressionSyntaxValidator::class, $constraint->validatedBy());
    }

    /**
     * @dataProvider provideServiceValidatedConstraints
     */
    public function testValidatedByService(ExpressionSyntax $constraint)
    {
        self::assertSame('my_service', $constraint->validatedBy());
    }

    public static function provideServiceValidatedConstraints(): iterable
    {
        yield 'Doctrine style' => [new ExpressionSyntax(['service' => 'my_service'])];

        yield 'named arguments' => [new ExpressionSyntax(service: 'my_service')];

        $metadata = new ClassMetadata(ExpressionSyntaxDummy::class);
        self::assertTrue((new AnnotationLoader())->loadClassMetadata($metadata));

        yield 'attribute' => [$metadata->properties['b']->constraints[0]];
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(ExpressionSyntaxDummy::class);
        self::assertTrue((new AnnotationLoader())->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertNull($aConstraint->service);
        self::assertNull($aConstraint->allowedVariables);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame('my_service', $bConstraint->service);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'ExpressionSyntaxDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['foo', 'bar'], $cConstraint->allowedVariables);
        self::assertSame(['my_group'], $cConstraint->groups);
    }
}

class ExpressionSyntaxDummy
{
    #[ExpressionSyntax]
    private $a;

    #[ExpressionSyntax(service: 'my_service', message: 'myMessage')]
    private $b;

    #[ExpressionSyntax(allowedVariables: ['foo', 'bar'], groups: ['my_group'])]
    private $c;
}
