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
use Symfony\Component\Validator\Constraints\BackedEnumValue;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

/**
 * @author Aurélien Pillevesse <aurelienpillevesse@hotmail.fr>
 */
class BackedEnumValueTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(EnumDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        /** @var BackedEnumValue $aConstraint */
        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame(MyStringEnum::class, $aConstraint->type);

        /** @var BackedEnumValue $bConstraint */
        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(MyStringEnum::class, $aConstraint->type);
        self::assertSame('myMessage', $bConstraint->message);

        /** @var BackedEnumValue $cConstraint */
        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(MyStringEnum::class, $aConstraint->type);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);

        /** @var BackedEnumValue $dConstraint */
        [$dConstraint] = $metadata->properties['d']->getConstraints();
        self::assertSame(MyStringEnum::class, $dConstraint->type);
        self::assertSame([MyStringEnum::YES], $dConstraint->except);
    }
}

class EnumDummy
{
    #[BackedEnumValue(type: MyStringEnum::class)]
    private $a;

    #[BackedEnumValue(type: MyStringEnum::class, message: 'myMessage')]
    private $b;

    #[BackedEnumValue(type: MyStringEnum::class, groups: ['my_group'], payload: 'some attached data')]
    private $c;

    #[BackedEnumValue(type: MyStringEnum::class, except: [MyStringEnum::YES])]
    private $d;
}

enum MyStringEnum: string
{
    case YES = 'yes';
    case NO = 'no';
}
