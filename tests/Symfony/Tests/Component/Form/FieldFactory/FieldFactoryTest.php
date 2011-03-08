<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\FieldFactory;

use Symfony\Component\Form\FieldFactory\FieldFactory;
use Symfony\Component\Form\FieldFactory\FieldFactoryGuess;
use Symfony\Component\Form\FieldFactory\FieldFactoryClassGuess;

class FieldFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConstructThrowsExceptionIfNoGuesser()
    {
        new FieldFactory(array(new \stdClass()));
    }

    public function testGetInstanceCreatesClassWithHighestConfidence()
    {
        $guesser1 = $this->getMock('Symfony\Component\Form\FieldFactory\FieldFactoryGuesserInterface');
        $guesser1->expects($this->once())
                ->method('guessClass')
                ->with($this->equalTo('Application\Author'), $this->equalTo('firstName'))
                ->will($this->returnValue(new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TextField',
                    array('max_length' => 10),
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                )));

        $guesser2 = $this->getMock('Symfony\Component\Form\FieldFactory\FieldFactoryGuesserInterface');
        $guesser2->expects($this->once())
                ->method('guessClass')
                ->with($this->equalTo('Application\Author'), $this->equalTo('firstName'))
                ->will($this->returnValue(new FieldFactoryClassGuess(
                    'Symfony\Component\Form\PasswordField',
                    array('max_length' => 7),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                )));

        $factory = new FieldFactory(array($guesser1, $guesser2));
        $field = $factory->getInstance('Application\Author', 'firstName');

        $this->assertEquals('Symfony\Component\Form\PasswordField', get_class($field));
        $this->assertEquals(7, $field->getMaxLength());
    }

    public function testGetInstanceThrowsExceptionIfNoClassIsFound()
    {
        $guesser = $this->getMock('Symfony\Component\Form\FieldFactory\FieldFactoryGuesserInterface');
        $guesser->expects($this->once())
                ->method('guessClass')
                ->with($this->equalTo('Application\Author'), $this->equalTo('firstName'))
                ->will($this->returnValue(null));

        $factory = new FieldFactory(array($guesser));

        $this->setExpectedException('\RuntimeException');

        $field = $factory->getInstance('Application\Author', 'firstName');
    }

    public function testOptionsCanBeOverridden()
    {
        $guesser = $this->getMock('Symfony\Component\Form\FieldFactory\FieldFactoryGuesserInterface');
        $guesser->expects($this->once())
                ->method('guessClass')
                ->with($this->equalTo('Application\Author'), $this->equalTo('firstName'))
                ->will($this->returnValue(new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TextField',
                    array('max_length' => 10),
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                )));

        $factory = new FieldFactory(array($guesser));
        $field = $factory->getInstance('Application\Author', 'firstName', array('max_length' => 11));

        $this->assertEquals('Symfony\Component\Form\TextField', get_class($field));
        $this->assertEquals(11, $field->getMaxLength());
    }

    public function testGetInstanceUsesMaxLengthIfFoundAndTextField()
    {
        $guesser1 = $this->getMock('Symfony\Component\Form\FieldFactory\FieldFactoryGuesserInterface');
        $guesser1->expects($this->once())
                ->method('guessClass')
                ->with($this->equalTo('Application\Author'), $this->equalTo('firstName'))
                ->will($this->returnValue(new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TextField',
                    array('max_length' => 10),
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                )));
        $guesser1->expects($this->once())
                ->method('guessMaxLength')
                ->with($this->equalTo('Application\Author'), $this->equalTo('firstName'))
                ->will($this->returnValue(new FieldFactoryGuess(
                    15,
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                )));

        $guesser2 = $this->getMock('Symfony\Component\Form\FieldFactory\FieldFactoryGuesserInterface');
        $guesser2->expects($this->once())
                ->method('guessMaxLength')
                ->with($this->equalTo('Application\Author'), $this->equalTo('firstName'))
                ->will($this->returnValue(new FieldFactoryGuess(
                    20,
                    FieldFactoryGuess::HIGH_CONFIDENCE
                )));

        $factory = new FieldFactory(array($guesser1, $guesser2));
        $field = $factory->getInstance('Application\Author', 'firstName');

        $this->assertEquals('Symfony\Component\Form\TextField', get_class($field));
        $this->assertEquals(20, $field->getMaxLength());
    }

    public function testGetInstanceUsesMaxLengthIfFoundAndSubclassOfTextField()
    {
        $guesser = $this->getMock('Symfony\Component\Form\FieldFactory\FieldFactoryGuesserInterface');
        $guesser->expects($this->once())
                ->method('guessClass')
                ->with($this->equalTo('Application\Author'), $this->equalTo('firstName'))
                ->will($this->returnValue(new FieldFactoryClassGuess(
                    'Symfony\Component\Form\PasswordField',
                    array('max_length' => 10),
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                )));
        $guesser->expects($this->once())
                ->method('guessMaxLength')
                ->with($this->equalTo('Application\Author'), $this->equalTo('firstName'))
                ->will($this->returnValue(new FieldFactoryGuess(
                    15,
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                )));

        $factory = new FieldFactory(array($guesser));
        $field = $factory->getInstance('Application\Author', 'firstName');

        $this->assertEquals('Symfony\Component\Form\PasswordField', get_class($field));
        $this->assertEquals(15, $field->getMaxLength());
    }

    public function testGetInstanceUsesRequiredSettingWithHighestConfidence()
    {
        $guesser1 = $this->getMock('Symfony\Component\Form\FieldFactory\FieldFactoryGuesserInterface');
        $guesser1->expects($this->once())
                ->method('guessClass')
                ->with($this->equalTo('Application\Author'), $this->equalTo('firstName'))
                ->will($this->returnValue(new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TextField',
                    array(),
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                )));
        $guesser1->expects($this->once())
                ->method('guessRequired')
                ->with($this->equalTo('Application\Author'), $this->equalTo('firstName'))
                ->will($this->returnValue(new FieldFactoryGuess(
                    true,
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                )));

        $guesser2 = $this->getMock('Symfony\Component\Form\FieldFactory\FieldFactoryGuesserInterface');
        $guesser2->expects($this->once())
                ->method('guessRequired')
                ->with($this->equalTo('Application\Author'), $this->equalTo('firstName'))
                ->will($this->returnValue(new FieldFactoryGuess(
                    false,
                    FieldFactoryGuess::HIGH_CONFIDENCE
                )));

        $factory = new FieldFactory(array($guesser1, $guesser2));
        $field = $factory->getInstance('Application\Author', 'firstName');

        $this->assertFalse($field->isRequired());
    }
}