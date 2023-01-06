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
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class ImageTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(ImageDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertNull($aConstraint->minWidth);
        self::assertNull($aConstraint->maxWidth);
        self::assertNull($aConstraint->minHeight);
        self::assertNull($aConstraint->maxHeight);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(50, $bConstraint->minWidth);
        self::assertSame(200, $bConstraint->maxWidth);
        self::assertSame(50, $bConstraint->minHeight);
        self::assertSame(200, $bConstraint->maxHeight);
        self::assertSame(['Default', 'ImageDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(100000, $cConstraint->maxSize);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class ImageDummy
{
    #[Image]
    private $a;

    #[Image(minWidth: 50, maxWidth: 200, minHeight: 50, maxHeight: 200)]
    private $b;

    #[Image(maxSize: '100K', groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
