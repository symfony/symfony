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

class ImageValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;
    protected $path;
    protected $image;
    protected $imageLandscape;
    protected $imagePortrait;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new ImageValidator();
        $this->validator->initialize($this->context);
        $this->image = __DIR__.'/Fixtures/test.gif';
        $this->imageLandscape = __DIR__.'/Fixtures/test_landscape.gif';
        $this->imagePortrait = __DIR__.'/Fixtures/test_portrait.gif';
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new Image());
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Image());
    }

    public function testValidImage()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($this->image, new Image());
    }

    public function testValidSize()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Image(array(
            'minWidth' => 1,
            'maxWidth' => 2,
            'minHeight' => 1,
            'maxHeight' => 2,
        ));

        $this->validator->validate($this->image, $constraint);
    }

    public function testWidthTooSmall()
    {
        $constraint = new Image(array(
            'minWidth' => 3,
            'minWidthMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ width }}' => '2',
                '{{ min_width }}' => '3',
            ));

        $this->validator->validate($this->image, $constraint);
    }

    public function testWidthTooBig()
    {
        $constraint = new Image(array(
            'maxWidth' => 1,
            'maxWidthMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ width }}' => '2',
                '{{ max_width }}' => '1',
            ));

        $this->validator->validate($this->image, $constraint);
    }

    public function testHeightTooSmall()
    {
        $constraint = new Image(array(
            'minHeight' => 3,
            'minHeightMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ height }}' => '2',
                '{{ min_height }}' => '3',
            ));

        $this->validator->validate($this->image, $constraint);
    }

    public function testHeightTooBig()
    {
        $constraint = new Image(array(
            'maxHeight' => 1,
            'maxHeightMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ height }}' => '2',
                '{{ max_height }}' => '1',
            ));

        $this->validator->validate($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMinWidth()
    {
        $constraint = new Image(array(
            'minWidth' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMaxWidth()
    {
        $constraint = new Image(array(
            'maxWidth' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMinHeight()
    {
        $constraint = new Image(array(
            'minHeight' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMaxHeight()
    {
        $constraint = new Image(array(
            'maxHeight' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }

    public function testRatioTooSmall()
    {
        $constraint = new Image(array(
            'minRatio' => 2,
            'minRatioMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ ratio }}' => 1,
                '{{ min_ratio }}' => 2,
            ));

        $this->validator->validate($this->image, $constraint);
    }

    public function testRatioTooBig()
    {
        $constraint = new Image(array(
            'maxRatio' => 0.5,
            'maxRatioMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ ratio }}' => 1,
                '{{ max_ratio }}' => 0.5,
            ));

        $this->validator->validate($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMinRatio()
    {
        $constraint = new Image(array(
            'minRatio' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMaxRatio()
    {
        $constraint = new Image(array(
            'maxRatio' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }

    public function testSquareNotAllowed()
    {
        $constraint = new Image(array(
            'allowSquare' => false,
            'allowSquareMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ width }}' => 2,
                '{{ height }}' => 2,
            ));

        $this->validator->validate($this->image, $constraint);
    }

    public function testLandscapeNotAllowed()
    {
        $constraint = new Image(array(
            'allowLandscape' => false,
            'allowLandscapeMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ width }}' => 2,
                '{{ height }}' => 1,
            ));

        $this->validator->validate($this->imageLandscape, $constraint);
    }

    public function testPortraitNotAllowed()
    {
        $constraint = new Image(array(
            'allowPortrait' => false,
            'allowPortraitMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ width }}' => 1,
                '{{ height }}' => 2,
            ));

        $this->validator->validate($this->imagePortrait, $constraint);
    }
}
