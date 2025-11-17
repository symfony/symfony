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
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Validator\Constraints\Video;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class VideoTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!(new ExecutableFinder())->find('ffprobe')) {
            self::markTestSkipped('The ffprobe binary is required to run this test.');
        }
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(VideoDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        self::assertNull($aConstraint->minWidth);
        self::assertNull($aConstraint->maxWidth);
        self::assertNull($aConstraint->minHeight);
        self::assertNull($aConstraint->maxHeight);

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertSame(50, $bConstraint->minWidth);
        self::assertSame(200, $bConstraint->maxWidth);
        self::assertSame(50, $bConstraint->minHeight);
        self::assertSame(200, $bConstraint->maxHeight);
        self::assertSame(['Default', 'VideoDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        self::assertSame(100000, $cConstraint->maxSize);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class VideoDummy
{
    #[Video]
    private $a;

    #[Video(minWidth: 50, maxWidth: 200, minHeight: 50, maxHeight: 200)]
    private $b;

    #[Video(maxSize: '100K', groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
