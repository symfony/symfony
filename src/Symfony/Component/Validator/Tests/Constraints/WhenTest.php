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
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\When;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\WhenTestWithAttributes;

final class WhenTest extends TestCase
{
    /**
     * @group legacy
     */
    public function testMissingOptionsExceptionIsThrown()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('The options "expression", "constraints" must be set for constraint "Symfony\Component\Validator\Constraints\When".');

        new When([]);
    }

    public function testNonConstraintsAreRejected()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The value "foo" is not an instance of Constraint in constraint "Symfony\Component\Validator\Constraints\When"');
        new When('true', ['foo']);
    }

    public function testAttributes()
    {
        $loader = new AttributeLoader();
        $metadata = new ClassMetadata(WhenTestWithAttributes::class);

        self::assertTrue($loader->loadClassMetadata($metadata));

        [$classConstraint] = $metadata->getConstraints();

        self::assertInstanceOf(When::class, $classConstraint);
        self::assertSame('true', $classConstraint->expression);
        self::assertEquals([
            new Callback(
                callback: 'callback',
                groups: ['Default', 'WhenTestWithAttributes'],
            ),
        ], $classConstraint->constraints);

        [$fooConstraint] = $metadata->properties['foo']->getConstraints();

        self::assertInstanceOf(When::class, $fooConstraint);
        self::assertSame('true', $fooConstraint->expression);
        self::assertEquals([
            new NotNull(groups: ['Default', 'WhenTestWithAttributes']),
            new NotBlank(groups: ['Default', 'WhenTestWithAttributes']),
        ], $fooConstraint->constraints);
        self::assertSame(['Default', 'WhenTestWithAttributes'], $fooConstraint->groups);

        [$barConstraint] = $metadata->properties['bar']->getConstraints();

        self::assertInstanceOf(When::class, $fooConstraint);
        self::assertSame('false', $barConstraint->expression);
        self::assertEquals([
            new NotNull(groups: ['foo']),
            new NotBlank(groups: ['foo']),
        ], $barConstraint->constraints);
        self::assertSame(['foo'], $barConstraint->groups);

        [$quxConstraint] = $metadata->properties['qux']->getConstraints();

        self::assertInstanceOf(When::class, $quxConstraint);
        self::assertSame('true', $quxConstraint->expression);
        self::assertEquals([new NotNull(groups: ['foo'])], $quxConstraint->constraints);
        self::assertSame(['foo'], $quxConstraint->groups);

        [$bazConstraint] = $metadata->getters['baz']->getConstraints();

        self::assertInstanceOf(When::class, $bazConstraint);
        self::assertSame('true', $bazConstraint->expression);
        self::assertEquals([
            new NotNull(groups: ['Default', 'WhenTestWithAttributes']),
            new NotBlank(groups: ['Default', 'WhenTestWithAttributes']),
        ], $bazConstraint->constraints);
        self::assertSame(['Default', 'WhenTestWithAttributes'], $bazConstraint->groups);
    }
}
