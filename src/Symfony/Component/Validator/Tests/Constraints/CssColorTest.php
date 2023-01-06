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
use Symfony\Component\Validator\Constraints\CssColor;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
final class CssColorTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(CssColorDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame([CssColor::HEX_LONG, CssColor::HEX_SHORT], $aConstraint->formats);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame([CssColor::HEX_LONG], $bConstraint->formats);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'CssColorDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame([CssColor::HEX_SHORT], $cConstraint->formats);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
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
