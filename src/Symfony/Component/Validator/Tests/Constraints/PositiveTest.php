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
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class PositiveTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(PositiveDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame(0, $aConstraint->value);
        self::assertNull($aConstraint->propertyPath);
        self::assertSame(['Default', 'PositiveDummy'], $aConstraint->groups);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['foo'], $bConstraint->groups);
    }
}

class PositiveDummy
{
    #[Positive]
    private $a;

    #[Positive(message: 'myMessage', groups: ['foo'])]
    private $b;
}
