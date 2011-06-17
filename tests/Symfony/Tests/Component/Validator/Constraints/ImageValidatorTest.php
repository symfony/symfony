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

    protected function tearDown()
    {
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
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ width }}' => '2',
            '{{ minWidth }}' => '3',
        ));
    }

    public function testWidthTooBig()
    {
        $constraint = new Image(array(
            'maxWidth' => 1,
            'maxWidthMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid($this->image, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ width }}' => '2',
            '{{ maxWidth }}' => '1',
        ));
    }

    public function testHeightTooSmall()
    {
        $constraint = new Image(array(
            'minHeight' => 3,
            'minHeightMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid($this->image, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ height }}' => '2',
            '{{ minHeight }}' => '3',
        ));
    }

    public function testHeightTooBig()
    {
        $constraint = new Image(array(
            'maxHeight' => 1,
            'maxHeightMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid($this->image, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ height }}' => '2',
            '{{ maxHeight }}' => '1',
        ));
    }

    public function testInvalidMinWidth()
    {
        $constraint = new Image(array(
            'minWidth' => '1abc',
        ));

        $this->setExpectedException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $this->validator->isValid($this->image, $constraint);
    }

    public function testInvalidMaxWidth()
    {
        $constraint = new Image(array(
            'maxWidth' => '1abc',
        ));

        $this->setExpectedException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $this->validator->isValid($this->image, $constraint);
    }

    public function testInvalidMinHeight()
    {
        $constraint = new Image(array(
            'minHeight' => '1abc',
        ));

        $this->setExpectedException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $this->validator->isValid($this->image, $constraint);
    }

    public function testInvalidMaxHeight()
    {
        $constraint = new Image(array(
            'maxHeight' => '1abc',
        ));

        $this->setExpectedException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $this->validator->isValid($this->image, $constraint);
    }
}