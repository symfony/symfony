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
use Symfony\Component\Validator\Constraints\CardScheme;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class CardSchemeTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(CardSchemeDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        self::assertSame([CardScheme::MASTERCARD, CardScheme::VISA], $aConstraint->schemes);

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertSame([CardScheme::AMEX], $bConstraint->schemes);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'CardSchemeDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        self::assertSame([CardScheme::DINERS], $cConstraint->schemes);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }

    public function testMissingSchemes()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The options "schemes" must be set for constraint "%s".', CardScheme::class));

        new CardScheme(null);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSchemesInOptionsArray()
    {
        $constraint = new CardScheme(null, options: ['schemes' => [CardScheme::MASTERCARD]]);

        $this->assertSame([CardScheme::MASTERCARD], $constraint->schemes);
    }
}

class CardSchemeDummy
{
    #[CardScheme([CardScheme::MASTERCARD, CardScheme::VISA])]
    private $a;

    #[CardScheme(schemes: [CardScheme::AMEX], message: 'myMessage')]
    private $b;

    #[CardScheme(schemes: [CardScheme::DINERS], groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
