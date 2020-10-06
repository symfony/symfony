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
 */
class ImageValidatorTest extends ConstraintValidatorTestCase
{
    protected $context;

    /**
     * @var ImageValidator
     */
    protected $validator;

    protected $path;
    protected $image;
    protected $imageLandscape;
    protected $imagePortrait;
    protected $image4By3;
    protected $imageCorrupted;

    protected function createValidator()
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
        $this->imageCorrupted = __DIR__.'/Fixtures/test_corrupted.gif';
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

    public function provideConstraintsWithNotFoundMessage(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'notFoundMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(notFoundMessage: "myMessage");'),
            ];
        }
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

    public function provideMinWidthConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'minWidth' => 3,
            'minWidthMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(minWidth: 3, minWidthMessage: "myMessage");'),
            ];
        }
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

    public function provideMaxWidthConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'maxWidth' => 1,
            'maxWidthMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(maxWidth: 1, maxWidthMessage: "myMessage");'),
            ];
        }
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

    public function provideMinHeightConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'minHeight' => 3,
            'minHeightMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(minHeight: 3, minHeightMessage: "myMessage");'),
            ];
        }
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

    public function provideMaxHeightConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'maxHeight' => 1,
            'maxHeightMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(maxHeight: 1, maxHeightMessage: "myMessage");'),
            ];
        }
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

    public function provideMinPixelsConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'minPixels' => 5,
            'minPixelsMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(minPixels: 5, minPixelsMessage: "myMessage");'),
            ];
        }
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

    public function provideMaxPixelsConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'maxPixels' => 3,
            'maxPixelsMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(maxPixels: 3, maxPixelsMessage: "myMessage");'),
            ];
        }
    }

    public function testInvalidMinWidth()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $constraint = new Image([
            'minWidth' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    public function testInvalidMaxWidth()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $constraint = new Image([
            'maxWidth' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    public function testInvalidMinHeight()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $constraint = new Image([
            'minHeight' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    public function testInvalidMaxHeight()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $constraint = new Image([
            'maxHeight' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    public function testInvalidMinPixels()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $constraint = new Image([
            'minPixels' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    public function testInvalidMaxPixels()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
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

    public function provideMinRatioConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'minRatio' => 2,
            'minRatioMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(minRatio: 2, minRatioMessage: "myMessage");'),
            ];
        }
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

    public function provideMaxRatioConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'maxRatio' => 0.5,
            'maxRatioMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(maxRatio: 0.5, maxRatioMessage: "myMessage");'),
            ];
        }
    }

    public function testMaxRatioUsesTwoDecimalsOnly()
    {
        $constraint = new Image([
            'maxRatio' => 1.33,
        ]);

        $this->validator->validate($this->image4By3, $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidMinRatio()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $constraint = new Image([
            'minRatio' => '1abc',
        ]);

        $this->validator->validate($this->image, $constraint);
    }

    public function testInvalidMaxRatio()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
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

    public function provideAllowSquareConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'allowSquare' => false,
            'allowSquareMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(allowSquare: false, allowSquareMessage: "myMessage");'),
            ];
        }
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

    public function provideAllowLandscapeConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'allowLandscape' => false,
            'allowLandscapeMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(allowLandscape: false, allowLandscapeMessage: "myMessage");'),
            ];
        }
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

    public function provideAllowPortraitConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'allowPortrait' => false,
            'allowPortraitMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(allowPortrait: false, allowPortraitMessage: "myMessage");'),
            ];
        }
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

    public function provideDetectCorruptedConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'detectCorrupted' => true,
            'corruptedMessage' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'Named arguments' => [
                eval('return new \Symfony\Component\Validator\Constraints\Image(detectCorrupted: true, corruptedMessage: "myMessage");'),
            ];
        }
    }
}
