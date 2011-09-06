<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\ImageValidator;

class ImageValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;
    protected $path;
    protected $image;

    protected function setUp()
    {
        $this->validator = new ImageValidator();
        $this->image = __DIR__.'/Fixtures/test.gif';
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Image()));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new Image()));
    }

    public function testValidImage()
    {
        $this->assertTrue($this->validator->isValid($this->image, new Image()));
    }

    public function testValidSize()
    {
        $constraint = new Image(array(
            'minWidth' => 1,
            'maxWidth' => 2,
            'minHeight' => 1,
            'maxHeight' => 2,
        ));

        $this->assertTrue($this->validator->isValid($this->image, $constraint));
    }

    public function testWidthTooSmall()
    {
        $constraint = new Image(array(
            'minWidth' => 3,
            'minWidthMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid($this->image, $constraint));
        $this->assertEquals('myMessage', $this->validator->getMessageTemplate());
        $this->assertEquals(array(
            '{{ width }}' => '2',
            '{{ min_width }}' => '3',
        ), $this->validator->getMessageParameters());
    }

    public function testWidthTooBig()
    {
        $constraint = new Image(array(
            'maxWidth' => 1,
            'maxWidthMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid($this->image, $constraint));
        $this->assertEquals('myMessage', $this->validator->getMessageTemplate());
        $this->assertEquals(array(
            '{{ width }}' => '2',
            '{{ max_width }}' => '1',
        ), $this->validator->getMessageParameters());
    }

    public function testHeightTooSmall()
    {
        $constraint = new Image(array(
            'minHeight' => 3,
            'minHeightMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid($this->image, $constraint));
        $this->assertEquals('myMessage', $this->validator->getMessageTemplate());
        $this->assertEquals(array(
            '{{ height }}' => '2',
            '{{ min_height }}' => '3',
        ), $this->validator->getMessageParameters());
    }

    public function testHeightTooBig()
    {
        $constraint = new Image(array(
            'maxHeight' => 1,
            'maxHeightMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid($this->image, $constraint));
        $this->assertEquals('myMessage', $this->validator->getMessageTemplate());
        $this->assertEquals(array(
            '{{ height }}' => '2',
            '{{ max_height }}' => '1',
        ), $this->validator->getMessageParameters());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMinWidth()
    {
        $constraint = new Image(array(
            'minWidth' => '1abc',
        ));

        $this->validator->isValid($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMaxWidth()
    {
        $constraint = new Image(array(
            'maxWidth' => '1abc',
        ));

        $this->validator->isValid($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMinHeight()
    {
        $constraint = new Image(array(
            'minHeight' => '1abc',
        ));

        $this->validator->isValid($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMaxHeight()
    {
        $constraint = new Image(array(
            'maxHeight' => '1abc',
        ));

        $this->validator->isValid($this->image, $constraint);
    }
}