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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @requires extension fileinfo
 *
 * @extends ConstraintValidatorTestCase<ImageValidator>
 */
class ImageValidatorTest extends ConstraintValidatorTestCase
{
    protected $path;
    protected $image;
    protected $imageLandscape;
    protected $imagePortrait;
    protected $image4By3;
    protected $image16By9;
    protected $imageCorrupted;
    protected $notAnImage;

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
     *
     * @dataProvider provideConstraintsWithNotFoundMessage
     */
    public function testFileNotFound(Image $constraint)
    {
        $this->validator->validate('foobar', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ file }}', '"foobar"')
            ->setCode(Image::NOT_FOUND_ERROR)
            ->assertRaised();
    }

    public static function provideConstraintsWithNotFoundMessage(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'notFoundMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(notFoundMessage: 'myMessage'),
        ];
    }

    public function testValidSize()
    {
        $constraint = new Image([
            'minWidth' => 1,
            'maxWidth' => 2,
            'minHeight' => 1,
            'maxHeight' => 2,
        ]);

        $this->validator->validate($this->image, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider provideMinWidthConstraints
     */
    public function testWidthTooSmall(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '2')
            ->setParameter('{{ min_width }}', '3')
            ->setCode(Image::TOO_NARROW_ERROR)
            ->assertRaised();
    }

    public static function provideMinWidthConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'minWidth' => 3,
            'minWidthMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(minWidth: 3, minWidthMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideMaxWidthConstraints
     */
    public function testWidthTooBig(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '2')
            ->setParameter('{{ max_width }}', '1')
            ->setCode(Image::TOO_WIDE_ERROR)
            ->assertRaised();
    }

    public static function provideMaxWidthConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'maxWidth' => 1,
            'maxWidthMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(maxWidth: 1, maxWidthMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideMinHeightConstraints
     */
    public function testHeightTooSmall(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ min_height }}', '3')
            ->setCode(Image::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public static function provideMinHeightConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'minHeight' => 3,
            'minHeightMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(minHeight: 3, minHeightMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideMaxHeightConstraints
     */
    public function testHeightTooBig(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ max_height }}', '1')
            ->setCode(Image::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    public static function provideMaxHeightConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'maxHeight' => 1,
            'maxHeightMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(maxHeight: 1, maxHeightMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideMinPixelsConstraints
     */
    public function testPixelsTooFew(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ pixels }}', '4')
            ->setParameter('{{ min_pixels }}', '5')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ width }}', '2')
            ->setCode(Image::TOO_FEW_PIXEL_ERROR)
            ->assertRaised();
    }

    public static function provideMinPixelsConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'minPixels' => 5,
            'minPixelsMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(minPixels: 5, minPixelsMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideMaxPixelsConstraints
     */
    public function testPixelsTooMany(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ pixels }}', '4')
            ->setParameter('{{ max_pixels }}', '3')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ width }}', '2')
            ->setCode(Image::TOO_MANY_PIXEL_ERROR)
            ->assertRaised();
    }

    public static function provideMaxPixelsConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'maxPixels' => 3,
            'maxPixelsMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(maxPixels: 3, maxPixelsMessage: 'myMessage'),
        ];
    }

    public function testInvalidMinWidth()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $constraint = new Image([
            'minWidth' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    public function testInvalidMaxWidth()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $constraint = new Image([
            'maxWidth' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    public function testInvalidMinHeight()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $constraint = new Image([
            'minHeight' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    public function testInvalidMaxHeight()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $constraint = new Image([
            'maxHeight' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    public function testInvalidMinPixels()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $constraint = new Image([
            'minPixels' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    public function testInvalidMaxPixels()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $constraint = new Image([
            'maxPixels' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    /**
     * @dataProvider provideMinRatioConstraints
     */
    public function testRatioTooSmall(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1)
            ->setParameter('{{ min_ratio }}', 2)
            ->setCode(Image::RATIO_TOO_SMALL_ERROR)
            ->assertRaised();
    }

    public static function provideMinRatioConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'minRatio' => 2,
            'minRatioMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(minRatio: 2, minRatioMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideMaxRatioConstraints
     */
    public function testRatioTooBig(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1)
            ->setParameter('{{ max_ratio }}', 0.5)
            ->setCode(Image::RATIO_TOO_BIG_ERROR)
            ->assertRaised();
    }

    public static function provideMaxRatioConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'maxRatio' => 0.5,
            'maxRatioMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(maxRatio: 0.5, maxRatioMessage: 'myMessage'),
        ];
    }

    public function testMaxRatioUsesTwoDecimalsOnly()
    {
        $constraint = new Image([
            'maxRatio' => 1.33,
        ]);

        $this->validator->validate($this->image4By3, $constraint);

        $this->assertNoViolation();
    }

    public function testMinRatioUsesInputMoreDecimals()
    {
        $constraint = new Image([
            'minRatio' => 4 / 3,
        ]);

        $this->validator->validate($this->image4By3, $constraint);

        $this->assertNoViolation();
    }

    public function testMaxRatioUsesInputMoreDecimals()
    {
        $constraint = new Image([
            'maxRatio' => 16 / 9,
        ]);

        $this->validator->validate($this->image16By9, $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidMinRatio()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $constraint = new Image([
            'minRatio' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    public function testInvalidMaxRatio()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $constraint = new Image([
            'maxRatio' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    /**
     * @dataProvider provideAllowSquareConstraints
     */
    public function testSquareNotAllowed(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 2)
            ->setParameter('{{ height }}', 2)
            ->setCode(Image::SQUARE_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public static function provideAllowSquareConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'allowSquare' => false,
            'allowSquareMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(allowSquare: false, allowSquareMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideAllowLandscapeConstraints
     */
    public function testLandscapeNotAllowed(Image $constraint)
    {
        $this->validator->validate($this->imageLandscape, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 2)
            ->setParameter('{{ height }}', 1)
            ->setCode(Image::LANDSCAPE_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public static function provideAllowLandscapeConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'allowLandscape' => false,
            'allowLandscapeMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(allowLandscape: false, allowLandscapeMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideAllowPortraitConstraints
     */
    public function testPortraitNotAllowed(Image $constraint)
    {
        $this->validator->validate($this->imagePortrait, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 1)
            ->setParameter('{{ height }}', 2)
            ->setCode(Image::PORTRAIT_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public static function provideAllowPortraitConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'allowPortrait' => false,
            'allowPortraitMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(allowPortrait: false, allowPortraitMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideDetectCorruptedConstraints
     */
    public function testCorrupted(Image $constraint)
    {
        if (!\function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('This test require GD extension');
        }

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
            ->setParameter('{{ file }}', sprintf('"%s"', $this->notAnImage))
            ->setParameter('{{ type }}', '"text/plain"')
            ->setParameter('{{ types }}', '"image/*"')
            ->setParameter('{{ name }}', '"ccc.txt"')
            ->setCode(Image::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public static function provideDetectCorruptedConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'detectCorrupted' => true,
            'corruptedMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(detectCorrupted: true, corruptedMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideInvalidMimeTypeWithNarrowedSet
     */
    public function testInvalidMimeTypeWithNarrowedSet(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.')
            ->setParameter('{{ file }}', sprintf('"%s"', $this->image))
            ->setParameter('{{ type }}', '"image/gif"')
            ->setParameter('{{ types }}', '"image/jpeg", "image/png"')
            ->setParameter('{{ name }}', '"test.gif"')
            ->setCode(Image::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public static function provideInvalidMimeTypeWithNarrowedSet()
    {
        yield 'Doctrine style' => [new Image([
            'mimeTypes' => [
                'image/jpeg',
                'image/png',
            ],
        ])];
        yield 'Named arguments' => [
            new Image(mimeTypes: [
                'image/jpeg',
                'image/png',
            ]),
        ];
    }
}
