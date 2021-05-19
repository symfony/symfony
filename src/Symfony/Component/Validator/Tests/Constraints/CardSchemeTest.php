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
use Symfony\Component\Validator\Constraints\CardScheme;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class CardSchemeTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(CardSchemeDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame([CardScheme::MASTERCARD, CardScheme::VISA], $aConstraint->schemes);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame([CardScheme::AMEX], $bConstraint->schemes);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'CardSchemeDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame([CardScheme::DINERS], $cConstraint->schemes);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
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
