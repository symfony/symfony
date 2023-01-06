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
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class DateTimeTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(DateTimeDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame('Y-m-d H:i:s', $aConstraint->format);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame('d.m.Y', $bConstraint->format);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'DateTimeDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame('m/d/Y', $cConstraint->format);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class DateTimeDummy
{
    #[DateTime]
    private $a;

    #[DateTime(format: 'd.m.Y', message: 'myMessage')]
    private $b;

    #[DateTime('m/d/Y', groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
