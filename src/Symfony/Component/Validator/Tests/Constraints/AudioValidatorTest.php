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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Validator\Constraints\Audio;
use Symfony\Component\Validator\Constraints\AudioValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

#[RequiresPhpExtension('fileinfo')]
class AudioValidatorTest extends ConstraintValidatorTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!(new ExecutableFinder())->find('ffprobe')) {
            self::markTestSkipped('The ffprobe binary is required to run this test.');
        }
    }

    protected function createValidator(): AudioValidator
    {
        return new AudioValidator();
    }

    public function testNullIsValid()
    {
        $this->validate(null, new Audio());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validate('', new Audio());

        $this->assertNoViolation();
    }

    public function testValidAudio()
    {
        $this->validate(__DIR__.'/Fixtures/test.mp3', new Audio());

        $this->assertNoViolation();
    }

    public function testFileNotFound()
    {
        $constraint = new Audio(notFoundMessage: 'myMessage');

        $this->validate('foobar', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ file }}', '"foobar"')
            ->setCode(Audio::NOT_FOUND_ERROR)
            ->assertRaised();
    }

    public function testInvalidMimeType()
    {
        $path = __DIR__.'/Fixtures/test.gif';

        $this->validate($path, $constraint = new Audio());

        $this->assertSame('audio/*', $constraint->mimeTypes);

        $this->buildViolation('This file is not a valid audio file.')
            ->setParameter('{{ file }}', \sprintf('"%s"', $path))
            ->setParameter('{{ type }}', '"image/gif"')
            ->setParameter('{{ types }}', '"audio/*"')
            ->setParameter('{{ name }}', '"test.gif"')
            ->setCode(Audio::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public function testInvalidMimeTypeWithNarrowedSet()
    {
        $constraint = new Audio(mimeTypes: [
            'audio/flac',
            'audio/ogg',
        ]);

        $this->validate(__DIR__.'/Fixtures/test.mp3', $constraint);

        $this->buildViolation('The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.')
            ->setParameter('{{ file }}', \sprintf('"%s/Fixtures/test.mp3"', __DIR__))
            ->setParameter('{{ type }}', '"audio/mpeg"')
            ->setParameter('{{ types }}', '"audio/flac", "audio/ogg"')
            ->setParameter('{{ name }}', '"test.mp3"')
            ->setCode(Audio::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public function testCorruptedAudio()
    {
        $constraint = new Audio(allowedCodecs: ['mp3'], corruptedMessage: 'myMessage');

        $this->validate(__DIR__.'/Fixtures/test_corrupted.m4a', $constraint);

        $this->buildViolation('myMessage')
            ->setCode(Audio::CORRUPTED_AUDIO_ERROR)
            ->assertRaised();
    }

    public function testFileWithoutAudioStream()
    {
        $constraint = new Audio(
            mimeTypes: ['video/mp4'],
            allowedCodecs: ['mp3'],
            noAudioStreamMessage: 'myMessage',
        );

        $this->validate(__DIR__.'/Fixtures/test.mp4', $constraint);

        $this->buildViolation('myMessage')
            ->setCode(Audio::NO_AUDIO_STREAM_ERROR)
            ->assertRaised();
    }

    public function testMultipleAudioStreams()
    {
        $constraint = new Audio(minChannels: 1, multipleAudioStreamsMessage: 'myMessage');

        $this->validate(__DIR__.'/Fixtures/test_multi_stream.ogg', $constraint);

        $this->buildViolation('myMessage')
            ->setCode(Audio::MULTIPLE_AUDIO_STREAMS_ERROR)
            ->assertRaised();
    }

    public function testAllowedCodec()
    {
        $this->validate(__DIR__.'/Fixtures/test.mp3', new Audio(allowedCodecs: ['MP3', 'aac']));

        $this->assertNoViolation();
    }

    public function testUnsupportedCodec()
    {
        $constraint = new Audio(allowedCodecs: ['aac'], unsupportedCodecMessage: 'myMessage');

        $this->validate(__DIR__.'/Fixtures/test.mp3', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ codec }}', 'mp3')
            ->setCode(Audio::UNSUPPORTED_AUDIO_CODEC_ERROR)
            ->assertRaised();
    }

    public function testAllowedContainer()
    {
        $this->validate(__DIR__.'/Fixtures/test.mp3', new Audio(allowedContainers: ['MP3', 'wav']));

        $this->assertNoViolation();
    }

    public function testUnsupportedContainer()
    {
        $constraint = new Audio(allowedContainers: ['wav'], unsupportedContainerMessage: 'myMessage');

        $this->validate(__DIR__.'/Fixtures/test.mp3', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ container }}', 'mp3')
            ->setCode(Audio::UNSUPPORTED_AUDIO_CONTAINER_ERROR)
            ->assertRaised();
    }

    // The duration checks use the PCM fixture: its duration is the sample count divided by
    // the sample rate, identical on every ffprobe build, while MP3 durations vary with how
    // the encoder delay is accounted for.
    public function testValidDuration()
    {
        $this->validate(__DIR__.'/Fixtures/test_8khz.wav', new Audio(minDuration: 0.5, maxDuration: 5));

        $this->assertNoViolation();
    }

    public function testDurationTooShort()
    {
        $constraint = new Audio(minDuration: 5, minDurationMessage: 'myMessage');

        $this->validate(__DIR__.'/Fixtures/test_8khz.wav', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ duration }}', '1')
            ->setParameter('{{ min_duration }}', '5')
            ->setCode(Audio::TOO_SHORT_ERROR)
            ->assertRaised();
    }

    public function testDurationTooLong()
    {
        $constraint = new Audio(maxDuration: 0.5, maxDurationMessage: 'myMessage');

        $this->validate(__DIR__.'/Fixtures/test_8khz.wav', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ duration }}', '1')
            ->setParameter('{{ max_duration }}', '0.5')
            ->setCode(Audio::TOO_LONG_ERROR)
            ->assertRaised();
    }

    public function testValidBitrate()
    {
        $this->validate(__DIR__.'/Fixtures/test.mp3', new Audio(minBitrate: 64000, maxBitrate: 320000));

        $this->assertNoViolation();
    }

    public function testBitrateTooLow()
    {
        $constraint = new Audio(minBitrate: 192000, minBitrateMessage: 'myMessage');

        $this->validate(__DIR__.'/Fixtures/test.mp3', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ bitrate }}', '128000')
            ->setParameter('{{ min_bitrate }}', '192000')
            ->setCode(Audio::BITRATE_TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function testBitrateTooHigh()
    {
        $constraint = new Audio(maxBitrate: 64000, maxBitrateMessage: 'myMessage');

        $this->validate(__DIR__.'/Fixtures/test.mp3', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ bitrate }}', '128000')
            ->setParameter('{{ max_bitrate }}', '64000')
            ->setCode(Audio::BITRATE_TOO_HIGH_ERROR)
            ->assertRaised();
    }

    public function testAllowedSampleRate()
    {
        $this->validate(__DIR__.'/Fixtures/test.mp3', new Audio(allowedSampleRates: [44100, 48000]));

        $this->assertNoViolation();
    }

    public function testUnsupportedSampleRate()
    {
        $constraint = new Audio(allowedSampleRates: [44100], unsupportedSampleRateMessage: 'myMessage');

        $this->validate(__DIR__.'/Fixtures/test_8khz.wav', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ sample_rate }}', '8000')
            ->setCode(Audio::UNSUPPORTED_SAMPLE_RATE_ERROR)
            ->assertRaised();
    }

    public function testValidChannels()
    {
        $this->validate(__DIR__.'/Fixtures/test.mp3', new Audio(minChannels: 1, maxChannels: 2));

        $this->assertNoViolation();
    }

    public function testTooFewChannels()
    {
        $constraint = new Audio(minChannels: 2, minChannelsMessage: 'myMessage');

        $this->validate(__DIR__.'/Fixtures/test_mono.mp3', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ channels }}', '1')
            ->setParameter('{{ min_channels }}', '2')
            ->setCode(Audio::TOO_FEW_CHANNELS_ERROR)
            ->assertRaised();
    }

    public function testTooManyChannels()
    {
        $constraint = new Audio(maxChannels: 1, maxChannelsMessage: 'myMessage');

        $this->validate(__DIR__.'/Fixtures/test.mp3', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ channels }}', '2')
            ->setParameter('{{ max_channels }}', '1')
            ->setCode(Audio::TOO_MANY_CHANNELS_ERROR)
            ->assertRaised();
    }

    public function testInvalidMinDuration()
    {
        $constraint = new Audio(minDuration: -1);

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessageIs('"-1" is not a valid minimum duration.');

        $this->validate(__DIR__.'/Fixtures/test.mp3', $constraint);
    }

    public function testInvalidMaxBitrate()
    {
        $constraint = new Audio(maxBitrate: -1);

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessageIs('"-1" is not a valid maximum bitrate.');

        $this->validate(__DIR__.'/Fixtures/test.mp3', $constraint);
    }

    public function testInvalidMinChannels()
    {
        $constraint = new Audio(minChannels: -1);

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessageIs('"-1" is not a valid minimum amount of channels.');

        $this->validate(__DIR__.'/Fixtures/test.mp3', $constraint);
    }

    public function testZeroMaxDurationIsInvalid()
    {
        $constraint = new Audio(maxDuration: 0);

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessageIs('"0" is not a valid maximum duration.');

        $this->validate(__DIR__.'/Fixtures/test.mp3', $constraint);
    }

    public function testZeroMaxBitrateIsInvalid()
    {
        $constraint = new Audio(maxBitrate: 0);

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessageIs('"0" is not a valid maximum bitrate.');

        $this->validate(__DIR__.'/Fixtures/test.mp3', $constraint);
    }

    public function testZeroMaxChannelsIsInvalid()
    {
        $constraint = new Audio(maxChannels: 0);

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessageIs('"0" is not a valid maximum amount of channels.');

        $this->validate(__DIR__.'/Fixtures/test.mp3', $constraint);
    }

    public function testZeroMinimumsAreAccepted()
    {
        $this->validate(__DIR__.'/Fixtures/test.mp3', new Audio(minDuration: 0, minBitrate: 0, minChannels: 0));

        $this->assertNoViolation();
    }
}
