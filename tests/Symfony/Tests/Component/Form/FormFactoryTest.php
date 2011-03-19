<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\FormFactory;

use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\Type\Guesser\Guess;
use Symfony\Component\Form\Type\Guesser\ValueGuess;
use Symfony\Component\Form\Type\Guesser\TypeGuess;

class FormFactoryTest extends \PHPUnit_Framework_TestCase
{
    private $typeLoader;

    private $factory;

    protected function setUp()
    {
        $this->typeLoader = $this->getMock('Symfony\Component\Form\Type\Loader\TypeLoaderInterface');
        $this->factory = new FormFactory($this->typeLoader);
    }

    public function testCreateBuilderForPropertyCreatesFieldWithHighestConfidence()
    {
        $guesser1 = $this->getMock('Symfony\Component\Form\Type\Guesser\TypeGuesserInterface');
        $guesser1->expects($this->once())
            ->method('guessType')
            ->with('Application\Author', 'firstName')
            ->will($this->returnValue(new TypeGuess(
                'text',
                array('max_length' => 10),
                Guess::MEDIUM_CONFIDENCE
            )));

        $guesser2 = $this->getMock('Symfony\Component\Form\Type\Guesser\TypeGuesserInterface');
        $guesser2->expects($this->once())
            ->method('guessType')
            ->with('Application\Author', 'firstName')
            ->will($this->returnValue(new TypeGuess(
                'password',
                array('max_length' => 7),
                Guess::HIGH_CONFIDENCE
            )));

        $factory = $this->createMockFactory(array('createBuilder'));
        $factory->addGuesser($guesser1);
        $factory->addGuesser($guesser2);

        $factory->expects($this->once())
            ->method('createBuilder')
            ->with('password', 'firstName', array('max_length' => 7))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertEquals('builderInstance', $builder);
    }

    public function testCreateBuilderCreatesTextFieldIfNoGuess()
    {
        $guesser = $this->getMock('Symfony\Component\Form\Type\Guesser\TypeGuesserInterface');
        $guesser->expects($this->once())
                ->method('guessType')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(null));

        $factory = $this->createMockFactory(array('createBuilder'));
        $factory->addGuesser($guesser);

        $factory->expects($this->once())
            ->method('createBuilder')
            ->with('text', 'firstName')
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertEquals('builderInstance', $builder);
    }

    public function testOptionsCanBeOverridden()
    {
        $guesser = $this->getMock('Symfony\Component\Form\Type\Guesser\TypeGuesserInterface');
        $guesser->expects($this->once())
                ->method('guessType')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new TypeGuess(
                    'text',
                    array('max_length' => 10),
                    Guess::MEDIUM_CONFIDENCE
                )));

        $factory = $this->createMockFactory(array('createBuilder'));
        $factory->addGuesser($guesser);

        $factory->expects($this->once())
            ->method('createBuilder')
            ->with('text', 'firstName', array('max_length' => 11))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName',
            array('max_length' => 11)
        );

        $this->assertEquals('builderInstance', $builder);
    }

    public function testCreateBuilderUsesMaxLengthIfFound()
    {
        $guesser1 = $this->getMock('Symfony\Component\Form\Type\Guesser\TypeGuesserInterface');
        $guesser1->expects($this->once())
                ->method('guessMaxLength')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    15,
                    Guess::MEDIUM_CONFIDENCE
                )));

        $guesser2 = $this->getMock('Symfony\Component\Form\Type\Guesser\TypeGuesserInterface');
        $guesser2->expects($this->once())
                ->method('guessMaxLength')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    20,
                    Guess::HIGH_CONFIDENCE
                )));

        $factory = $this->createMockFactory(array('createBuilder'));
        $factory->addGuesser($guesser1);
        $factory->addGuesser($guesser2);

        $factory->expects($this->once())
            ->method('createBuilder')
            ->with('text', 'firstName', array('max_length' => 20))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $builder);
    }

    public function testCreateBuilderUsesRequiredSettingWithHighestConfidence()
    {
        $guesser1 = $this->getMock('Symfony\Component\Form\Type\Guesser\TypeGuesserInterface');
        $guesser1->expects($this->once())
                ->method('guessRequired')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    true,
                    Guess::MEDIUM_CONFIDENCE
                )));

        $guesser2 = $this->getMock('Symfony\Component\Form\Type\Guesser\TypeGuesserInterface');
        $guesser2->expects($this->once())
                ->method('guessRequired')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    false,
                    Guess::HIGH_CONFIDENCE
                )));

        $factory = $this->createMockFactory(array('createBuilder'));
        $factory->addGuesser($guesser1);
        $factory->addGuesser($guesser2);

        $factory->expects($this->once())
            ->method('createBuilder')
            ->with('text', 'firstName', array('required' => false))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $builder);
    }

    private function createMockFactory(array $methods = array())
    {
        return $this->getMockBuilder('Symfony\Component\Form\FormFactory')
            ->setMethods($methods)
            ->setConstructorArgs(array($this->typeLoader))
            ->getMock();
    }
}