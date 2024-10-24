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

use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\ImageValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @requires extension fileinfo
 *
 * @extends ConstraintValidatorTestCase<ImageValidator>
 */
class ImageValidatorTest extends ConstraintValidatorTestCase
{
    protected string $path;
    protected string $image;
    protected string $imageLandscape;
    protected string $imagePortrait;
    protected string $image4By3;
    protected string $image16By9;
    protected string $imageCorrupted;
    protected string $notAnImage;

    protected function createValidator(): ImageValidator
    {
        return new ImageValidator();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->image = __DIR__.'/Fixtures/test.gif';
        $this->imageLandscape = __DIR__.'/Fixtures/test_landscape.gif';
        $this->imagePortrait = __DIR__.'/Fixtures/test_portrait.gif';
        $this->image4By3 = __DIR__.'/Fixtures/test_4by3.gif';
        $this->image16By9 = __DIR__.'/Fixtures/test_16by9.gif';
        $this->imageCorrupted = __DIR__.'/Fixtures/test_corrupted.gif';
        $this->notAnImage = __DIR__.'/Fixtures/ccc.txt';
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Image());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Image());

        $this->assertNoViolation();
    }

    public function testValidImage()
    {
        $this->validator->validate($this->image, new Image());

        $this->assertNoViolation();
    }

    /**
     * Checks that the logic from FileValidator still works.
     */
    public function testFileNotFound()
    {
        $this->validator->validate('foobar', new Image(notFoundMessage: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ file }}', '"foobar"')
            ->setCode(Image::NOT_FOUND_ERROR)
            ->assertRaised();
    }

    /**
     * Checks that the logic from FileValidator still works.
     *
     * @group legacy
     */
    public function testFileNotFoundDoctrineStyle()
    {
        $this->validator->validate('foobar', new Image([
            'notFoundMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ file }}', '"foobar"')
            ->setCode(Image::NOT_FOUND_ERROR)
            ->assertRaised();
    }

    public function testValidSize()
    {
        $constraint = new Image(
            minWidth: 1,
            maxWidth: 2,
            minHeight: 1,
            maxHeight: 2,
        );

        $this->validator->validate($this->image, $constraint);

        $this->assertNoViolation();
    }

    public function testWidthTooSmall()
    {
        $this->validator->validate($this->image, new Image(minWidth: 3, minWidthMessage: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '2')
            ->setParameter('{{ min_width }}', '3')
            ->setCode(Image::TOO_NARROW_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testWidthTooSmallDoctrineStyle()
    {
        $this->validator->validate($this->image, new Image([
            'minWidth' => 3,
            'minWidthMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '2')
            ->setParameter('{{ min_width }}', '3')
            ->setCode(Image::TOO_NARROW_ERROR)
            ->assertRaised();
    }

    public function testWidthTooBig()
    {
        $this->validator->validate($this->image, new Image(maxWidth: 1, maxWidthMessage: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '2')
            ->setParameter('{{ max_width }}', '1')
            ->setCode(Image::TOO_WIDE_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testWidthTooBigDoctrineStyle()
    {
        $this->validator->validate($this->image, new Image([
            'maxWidth' => 1,
            'maxWidthMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '2')
            ->setParameter('{{ max_width }}', '1')
            ->setCode(Image::TOO_WIDE_ERROR)
            ->assertRaised();
    }

    public function testHeightTooSmall()
    {
        $this->validator->validate($this->image, new Image(minHeight: 3, minHeightMessage: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ min_height }}', '3')
            ->setCode(Image::TOO_LOW_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testHeightTooSmallDoctrineStyle()
    {
        $this->validator->validate($this->image, new Image([
            'minHeight' => 3,
            'minHeightMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ min_height }}', '3')
            ->setCode(Image::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function testHeightTooBig()
    {
        $this->validator->validate($this->image, new Image(maxHeight: 1, maxHeightMessage: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ max_height }}', '1')
            ->setCode(Image::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testHeightTooBigDoctrineStyle()
    {
        $this->validator->validate($this->image, new Image([
            'maxHeight' => 1,
            'maxHeightMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ max_height }}', '1')
            ->setCode(Image::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    public function testPixelsTooFew()
    {
        $this->validator->validate($this->image, new Image(minPixels: 5, minPixelsMessage: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ pixels }}', '4')
            ->setParameter('{{ min_pixels }}', '5')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ width }}', '2')
            ->setCode(Image::TOO_FEW_PIXEL_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testPixelsTooFewDoctrineStyle()
    {
        $this->validator->validate($this->image, new Image([
            'minPixels' => 5,
            'minPixelsMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ pixels }}', '4')
            ->setParameter('{{ min_pixels }}', '5')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ width }}', '2')
            ->setCode(Image::TOO_FEW_PIXEL_ERROR)
            ->assertRaised();
    }

    public function testPixelsTooMany()
    {
        $this->validator->validate($this->image, new Image(maxPixels: 3, maxPixelsMessage: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ pixels }}', '4')
            ->setParameter('{{ max_pixels }}', '3')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ width }}', '2')
            ->setCode(Image::TOO_MANY_PIXEL_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testPixelsTooManyDoctrineStyle()
    {
        $this->validator->validate($this->image, new Image([
            'maxPixels' => 3,
            'maxPixelsMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ pixels }}', '4')
            ->setParameter('{{ max_pixels }}', '3')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ width }}', '2')
            ->setCode(Image::TOO_MANY_PIXEL_ERROR)
            ->assertRaised();
    }

    public function testRatioTooSmall()
    {
        $this->validator->validate($this->image, new Image(minRatio: 2, minRatioMessage: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1)
            ->setParameter('{{ min_ratio }}', 2)
            ->setCode(Image::RATIO_TOO_SMALL_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testRatioTooSmallDoctrineStyle()
    {
        $this->validator->validate($this->image, new Image([
            'minRatio' => 2,
            'minRatioMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1)
            ->setParameter('{{ min_ratio }}', 2)
            ->setCode(Image::RATIO_TOO_SMALL_ERROR)
            ->assertRaised();
    }

    public function testRatioTooBig()
    {
        $this->validator->validate($this->image, new Image(maxRatio: 0.5, maxRatioMessage: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1)
            ->setParameter('{{ max_ratio }}', 0.5)
            ->setCode(Image::RATIO_TOO_BIG_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testRatioTooBigDoctrineStyle()
    {
        $this->validator->validate($this->image, new Image([
            'maxRatio' => 0.5,
            'maxRatioMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1)
            ->setParameter('{{ max_ratio }}', 0.5)
            ->setCode(Image::RATIO_TOO_BIG_ERROR)
            ->assertRaised();
    }

    public function testMaxRatioUsesTwoDecimalsOnly()
    {
        $constraint = new Image(maxRatio: 1.33);

        $this->validator->validate($this->image4By3, $constraint);

        $this->assertNoViolation();
    }

    public function testMinRatioUsesInputMoreDecimals()
    {
        $constraint = new Image(minRatio: 4 / 3);

        $this->validator->validate($this->image4By3, $constraint);

        $this->assertNoViolation();
    }

    public function testMaxRatioUsesInputMoreDecimals()
    {
        $constraint = new Image(maxRatio: 16 / 9);

        $this->validator->validate($this->image16By9, $constraint);

        $this->assertNoViolation();
    }

    public function testSquareNotAllowed()
    {
        $this->validator->validate($this->image, new Image(allowSquare: false, allowSquareMessage: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 2)
            ->setParameter('{{ height }}', 2)
            ->setCode(Image::SQUARE_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testSquareNotAllowedDoctrineStyle()
    {
        $this->validator->validate($this->image, new Image([
            'allowSquare' => false,
            'allowSquareMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 2)
            ->setParameter('{{ height }}', 2)
            ->setCode(Image::SQUARE_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testLandscapeNotAllowed()
    {
        $this->validator->validate($this->imageLandscape, new Image(allowLandscape: false, allowLandscapeMessage: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 2)
            ->setParameter('{{ height }}', 1)
            ->setCode(Image::LANDSCAPE_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testLandscapeNotAllowedDoctrineStyle()
    {
        $this->validator->validate($this->imageLandscape, new Image([
            'allowLandscape' => false,
            'allowLandscapeMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 2)
            ->setParameter('{{ height }}', 1)
            ->setCode(Image::LANDSCAPE_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testPortraitNotAllowed()
    {
        $this->validator->validate($this->imagePortrait, new Image(allowPortrait: false, allowPortraitMessage: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 1)
            ->setParameter('{{ height }}', 2)
            ->setCode(Image::PORTRAIT_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testPortraitNotAllowedDoctrineStyle()
    {
        $this->validator->validate($this->imagePortrait, new Image([
            'allowPortrait' => false,
            'allowPortraitMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 1)
            ->setParameter('{{ height }}', 2)
            ->setCode(Image::PORTRAIT_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testCorrupted()
    {
        if (!\function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('This test require GD extension');
        }

        $constraint = new Image(detectCorrupted: true, corruptedMessage: 'myMessage');

        $this->validator->validate($this->image, $constraint);

        $this->assertNoViolation();

        $this->validator->validate($this->imageCorrupted, $constraint);

        $this->buildViolation('myMessage')
            ->setCode(Image::CORRUPTED_IMAGE_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testCorruptedDoctrineStyle()
    {
        if (!\function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('This test require GD extension');
        }

        $constraint = new Image([
            'detectCorrupted' => true,
            'corruptedMessage' => 'myMessage',
        ]);

        $this->validator->validate($this->image, $constraint);

        $this->assertNoViolation();

        $this->validator->validate($this->imageCorrupted, $constraint);

        $this->buildViolation('myMessage')
            ->setCode(Image::CORRUPTED_IMAGE_ERROR)
            ->assertRaised();
    }

    public function testInvalidMimeType()
    {
        $this->validator->validate($this->notAnImage, $constraint = new Image());

        $this->assertSame('image/*', $constraint->mimeTypes);

        $this->buildViolation('This file is not a valid image.')
            ->setParameter('{{ file }}', \sprintf('"%s"', $this->notAnImage))
            ->setParameter('{{ type }}', '"text/plain"')
            ->setParameter('{{ types }}', '"image/*"')
            ->setParameter('{{ name }}', '"ccc.txt"')
            ->setCode(Image::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public function testInvalidMimeTypeWithNarrowedSet()
    {
        $this->validator->validate($this->image, new Image(mimeTypes: [
            'image/jpeg',
            'image/png',
        ]));

        $this->buildViolation('The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.')
            ->setParameter('{{ file }}', \sprintf('"%s"', $this->image))
            ->setParameter('{{ type }}', '"image/gif"')
            ->setParameter('{{ types }}', '"image/jpeg", "image/png"')
            ->setParameter('{{ name }}', '"test.gif"')
            ->setCode(Image::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testInvalidMimeTypeWithNarrowedSetDoctrineStyle()
    {
        $this->validator->validate($this->image, new Image([
            'mimeTypes' => [
                'image/jpeg',
                'image/png',
            ],
        ]));

        $this->buildViolation('The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.')
            ->setParameter('{{ file }}', sprintf('"%s"', $this->image))
            ->setParameter('{{ type }}', '"image/gif"')
            ->setParameter('{{ types }}', '"image/jpeg", "image/png"')
            ->setParameter('{{ name }}', '"test.gif"')
            ->setCode(Image::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }
}
