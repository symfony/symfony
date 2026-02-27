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
use Symfony\Component\Validator\Constraints\Video;
use Symfony\Component\Validator\Constraints\VideoValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

#[RequiresPhpExtension('fileinfo')]
class VideoValidatorTest extends ConstraintValidatorTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!(new ExecutableFinder())->find('ffprobe')) {
            self::markTestSkipped('The ffprobe binary is required to run this test.');
        }
    }

    protected function createValidator(): VideoValidator
    {
        return new VideoValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Video());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Video());

        $this->assertNoViolation();
    }

    public function testValidVideo()
    {
        $this->validator->validate(__DIR__.'/Fixtures/test.mp4', new Video());

        $this->assertNoViolation();
    }

    public function testFileNotFound()
    {
        $constraint = new Video(notFoundMessage: 'myMessage');
        $this->validator->validate('foobar', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ file }}', '"foobar"')
            ->setCode(Video::NOT_FOUND_ERROR)
            ->assertRaised();
    }

    public function testValidSize()
    {
        $constraint = new Video(
            minWidth: 1,
            maxWidth: 2,
            minHeight: 1,
            maxHeight: 2,
        );

        $this->validator->validate(__DIR__.'/Fixtures/test.mp4', $constraint);

        $this->assertNoViolation();
    }

    public function testWidthTooSmall()
    {
        $constraint = new Video(minWidth: 3, minWidthMessage: 'myMessage');
        $this->validator->validate(__DIR__.'/Fixtures/test.mp4', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '2')
            ->setParameter('{{ min_width }}', '3')
            ->setCode(Video::TOO_NARROW_ERROR)
            ->assertRaised();
    }

    public function testWidthTooBig()
    {
        $constraint = new Video(maxWidth: 1, maxWidthMessage: 'myMessage');
        $this->validator->validate(__DIR__.'/Fixtures/test.mp4', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '2')
            ->setParameter('{{ max_width }}', '1')
            ->setCode(Video::TOO_WIDE_ERROR)
            ->assertRaised();
    }

    public function testHeightTooSmall()
    {
        $constraint = new Video(minHeight: 3, minHeightMessage: 'myMessage');
        $this->validator->validate(__DIR__.'/Fixtures/test.mp4', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ min_height }}', '3')
            ->setCode(Video::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function testHeightTooBig()
    {
        $constraint = new Video(maxHeight: 1, maxHeightMessage: 'myMessage');
        $this->validator->validate(__DIR__.'/Fixtures/test.mp4', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ max_height }}', '1')
            ->setCode(Video::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    public function testPixelsTooFew()
    {
        $constraint = new Video(minPixels: 5, minPixelsMessage: 'myMessage');
        $this->validator->validate(__DIR__.'/Fixtures/test.mp4', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ pixels }}', '4')
            ->setParameter('{{ min_pixels }}', '5')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ width }}', '2')
            ->setCode(Video::TOO_FEW_PIXEL_ERROR)
            ->assertRaised();
    }

    public function testPixelsTooMany()
    {
        $constraint = new Video(maxPixels: 3, maxPixelsMessage: 'myMessage');
        $this->validator->validate(__DIR__.'/Fixtures/test.mp4', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ pixels }}', '4')
            ->setParameter('{{ max_pixels }}', '3')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ width }}', '2')
            ->setCode(Video::TOO_MANY_PIXEL_ERROR)
            ->assertRaised();
    }

    public function testRatioTooSmall()
    {
        $constraint = new Video(minRatio: 2, minRatioMessage: 'myMessage');
        $this->validator->validate(__DIR__.'/Fixtures/test.mp4', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1)
            ->setParameter('{{ min_ratio }}', 2)
            ->setCode(Video::RATIO_TOO_SMALL_ERROR)
            ->assertRaised();
    }

    public function testRatioTooBig()
    {
        $constraint = new Video(maxRatio: 0.5, maxRatioMessage: 'myMessage');
        $this->validator->validate(__DIR__.'/Fixtures/test.mp4', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1)
            ->setParameter('{{ max_ratio }}', 0.5)
            ->setCode(Video::RATIO_TOO_BIG_ERROR)
            ->assertRaised();
    }

    public function testMaxRatioUsesTwoDecimalsOnly()
    {
        $constraint = new Video(maxRatio: 1.33);

        $this->validator->validate(__DIR__.'/Fixtures/test_4by3.mp4', $constraint);

        $this->assertNoViolation();
    }

    public function testMinRatioUsesInputMoreDecimals()
    {
        $constraint = new Video(minRatio: 4 / 3);

        $this->validator->validate(__DIR__.'/Fixtures/test_4by3.mp4', $constraint);

        $this->assertNoViolation();
    }

    public function testMaxRatioUsesInputMoreDecimals()
    {
        $constraint = new Video(maxRatio: 16 / 9);

        $this->validator->validate(__DIR__.'/Fixtures/test_16by9.mp4', $constraint);

        $this->assertNoViolation();
    }

    public function testSquareNotAllowed()
    {
        $constraint = new Video(allowSquare: false, allowSquareMessage: 'myMessage');
        $this->validator->validate(__DIR__.'/Fixtures/test.mp4', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 2)
            ->setParameter('{{ height }}', 2)
            ->setCode(Video::SQUARE_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testLandscapeNotAllowed()
    {
        $constraint = new Video(allowLandscape: false, allowLandscapeMessage: 'myMessage');
        $this->validator->validate(__DIR__.'/Fixtures/test_landscape.mp4', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 2)
            ->setParameter('{{ height }}', 1)
            ->setCode(Video::LANDSCAPE_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testPortraitNotAllowed()
    {
        $constraint = new Video(allowPortrait: false, allowPortraitMessage: 'myMessage');
        $this->validator->validate(__DIR__.'/Fixtures/test_portrait.mp4', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 1)
            ->setParameter('{{ height }}', 2)
            ->setCode(Video::PORTRAIT_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testCorrupted()
    {
        $constraint = new Video(maxRatio: 1);

        $this->validator->validate(__DIR__.'/Fixtures/test_corrupted.mp4', $constraint);

        $this->buildViolation('The video file is corrupted.')
            ->setCode(Video::CORRUPTED_VIDEO_ERROR)
            ->assertRaised();
    }

    public function testInvalidMimeType()
    {
        $this->validator->validate(__DIR__.'/Fixtures/ccc.txt', $constraint = new Video());

        $this->assertSame('video/*', $constraint->mimeTypes);

        $this->buildViolation('This file is not a valid video.')
            ->setParameter('{{ file }}', \sprintf('"%s/Fixtures/ccc.txt"', __DIR__))
            ->setParameter('{{ type }}', '"text/plain"')
            ->setParameter('{{ types }}', '"video/*"')
            ->setParameter('{{ name }}', '"ccc.txt"')
            ->setCode(Video::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public function testInvalidMimeTypeWithNarrowedSet()
    {
        $constraint = new Video(mimeTypes: [
            'video/mkv',
            'video/mov',
        ]);
        $this->validator->validate(__DIR__.'/Fixtures/test.mp4', $constraint);

        $this->buildViolation('The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.')
            ->setParameter('{{ file }}', \sprintf('"%s/Fixtures/test.mp4"', __DIR__))
            ->setParameter('{{ type }}', '"video/mp4"')
            ->setParameter('{{ types }}', '"video/mkv", "video/mov"')
            ->setParameter('{{ name }}', '"test.mp4"')
            ->setCode(Video::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }
}
