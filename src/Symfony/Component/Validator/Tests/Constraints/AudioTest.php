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
use Symfony\Component\Validator\Constraints\Audio;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class AudioTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(AudioDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        self::assertSame('audio/*', $aConstraint->mimeTypes);
        self::assertNull($aConstraint->minDuration);
        self::assertNull($aConstraint->maxDuration);
        self::assertNull($aConstraint->minBitrate);
        self::assertNull($aConstraint->maxBitrate);
        self::assertNull($aConstraint->minChannels);
        self::assertNull($aConstraint->maxChannels);
        self::assertSame([], $aConstraint->allowedSampleRates);
        self::assertSame([], $aConstraint->allowedCodecs);
        self::assertSame([], $aConstraint->allowedContainers);

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertSame(1, $bConstraint->minDuration);
        self::assertSame(300, $bConstraint->maxDuration);
        self::assertSame(64000, $bConstraint->minBitrate);
        self::assertSame(320000, $bConstraint->maxBitrate);
        self::assertSame([44100, 48000], $bConstraint->allowedSampleRates);
        self::assertSame(1, $bConstraint->minChannels);
        self::assertSame(2, $bConstraint->maxChannels);
        self::assertSame(['mp3', 'aac'], $bConstraint->allowedCodecs);
        self::assertSame(['mp3', 'mp4'], $bConstraint->allowedContainers);
        self::assertSame(['Default', 'AudioDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        self::assertSame(100000, $cConstraint->maxSize);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class AudioDummy
{
    #[Audio]
    private $a;

    #[Audio(
        minDuration: 1,
        maxDuration: 300,
        minBitrate: 64000,
        maxBitrate: 320000,
        allowedSampleRates: [44100, 48000],
        minChannels: 1,
        maxChannels: 2,
        allowedCodecs: ['mp3', 'aac'],
        allowedContainers: ['mp3', 'mp4'],
    )]
    private $b;

    #[Audio(maxSize: '100K', groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
