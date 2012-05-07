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

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new ImageValidator();
        $this->validator->initialize($this->context);
        $this->image = __DIR__.'/Fixtures/test.gif';
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
        if (!class_exists('Symfony\Component\HttpFoundation\File\File')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($this->image, new Image());
    }

    public function testValidSize()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\File\File')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

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
        if (!class_exists('Symfony\Component\HttpFoundation\File\File')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

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
        if (!class_exists('Symfony\Component\HttpFoundation\File\File')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

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
        if (!class_exists('Symfony\Component\HttpFoundation\File\File')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

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
        if (!class_exists('Symfony\Component\HttpFoundation\File\File')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

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
        if (!class_exists('Symfony\Component\HttpFoundation\File\File')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

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
        if (!class_exists('Symfony\Component\HttpFoundation\File\File')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

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
        if (!class_exists('Symfony\Component\HttpFoundation\File\File')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

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
        if (!class_exists('Symfony\Component\HttpFoundation\File\File')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

        $constraint = new Image(array(
            'maxHeight' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }
}
