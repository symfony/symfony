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
use Symfony\Component\Validator\Constraints\CssColor;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
final class CssColorTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(CssColorDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        self::assertSame([CssColor::HEX_LONG, CssColor::HEX_SHORT], $aConstraint->formats);

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertSame([CssColor::HEX_LONG], $bConstraint->formats);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'CssColorDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        self::assertSame([CssColor::HEX_SHORT], $cConstraint->formats);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }

    public function testMissingPatternDoctrineStyle()
    {
        $constraint = new CssColor([]);

        $this->assertSame([
            CssColor::HEX_LONG,
            CssColor::HEX_LONG_WITH_ALPHA,
            CssColor::HEX_SHORT,
            CssColor::HEX_SHORT_WITH_ALPHA,
            CssColor::BASIC_NAMED_COLORS,
            CssColor::EXTENDED_NAMED_COLORS,
            CssColor::SYSTEM_COLORS,
            CssColor::KEYWORDS,
            CssColor::RGB,
            CssColor::RGBA,
            CssColor::HSL,
            CssColor::HSLA,
        ], $constraint->formats);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testDoctrineStyle()
    {
        $constraint = new CssColor(['formats' => CssColor::RGB]);

        $this->assertSame(CssColor::RGB, $constraint->formats);
    }
}

class CssColorDummy
{
    #[CssColor([CssColor::HEX_LONG, CssColor::HEX_SHORT])]
    private $a;

    #[CssColor(formats: CssColor::HEX_LONG, message: 'myMessage')]
    private $b;

    #[CssColor(formats: [CssColor::HEX_SHORT], groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
