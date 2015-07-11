<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * @group tty
 */
class QuestionHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testAskChoice()
    {
        $questionHelper = new QuestionHelper();

        $helperSet = new HelperSet(array(new FormatterHelper()));
        $questionHelper->setHelperSet($helperSet);

        $heroes = array('Superman', 'Batman', 'Spiderman');

        $questionHelper->setInputStream($this->getInputStream("\n1\n  1  \nFabien\n1\nFabien\n1\n0,2\n 0 , 2  \n\n\n"));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '2');
        // first answer is an empty answer, we're supposed to receive the default value
        $this->assertEquals('Spiderman', $questionHelper->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes);
        $this->assertEquals('Batman', $questionHelper->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $this->assertEquals('Batman', $questionHelper->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes);
        $question->setErrorMessage('Input "%s" is not a superhero!');
        $this->assertEquals('Batman', $questionHelper->ask($this->createInputInterfaceMock(), $output = $this->createOutputInterface(), $question));

        rewind($output->getStream());
        $stream = stream_get_contents($output->getStream());
        $this->assertContains('Input "Fabien" is not a superhero!', $stream);

        try {
            $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '1');
            $question->setMaxAttempts(1);
            $questionHelper->ask($this->createInputInterfaceMock(), $output = $this->createOutputInterface(), $question);
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('Value "Fabien" is invalid', $e->getMessage());
        }

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, null);
        $question->setMultiselect(true);

        $this->assertEquals(array('Batman'), $questionHelper->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $this->assertEquals(array('Superman', 'Spiderman'), $questionHelper->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $this->assertEquals(array('Superman', 'Spiderman'), $questionHelper->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '0,1');
        $question->setMultiselect(true);

        $this->assertEquals(array('Superman', 'Batman'), $questionHelper->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, ' 0 , 1 ');
        $question->setMultiselect(true);

        $this->assertEquals(array('Superman', 'Batman'), $questionHelper->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
    }

    public function testAsk()
    {
        $dialog = new QuestionHelper();

        $dialog->setInputStream($this->getInputStream("\n8AM\n"));

        $question = new Question('What time is it?', '2PM');
        $this->assertEquals('2PM', $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));

        $question = new Question('What time is it?', '2PM');
        $this->assertEquals('8AM', $dialog->ask($this->createInputInterfaceMock(), $output = $this->createOutputInterface(), $question));

        rewind($output->getStream());
        $this->assertEquals('What time is it?', stream_get_contents($output->getStream()));
    }

    public function testAskWithAutocomplete()
    {
        if (!$this->hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        // Acm<NEWLINE>
        // Ac<BACKSPACE><BACKSPACE>s<TAB>Test<NEWLINE>
        // <NEWLINE>
        // <UP ARROW><UP ARROW><NEWLINE>
        // <UP ARROW><UP ARROW><UP ARROW><UP ARROW><UP ARROW><TAB>Test<NEWLINE>
        // <DOWN ARROW><NEWLINE>
        // S<BACKSPACE><BACKSPACE><DOWN ARROW><DOWN ARROW><NEWLINE>
        // F00<BACKSPACE><BACKSPACE>oo<TAB><NEWLINE>
        $inputStream = $this->getInputStream("Acm\nAc\177\177s\tTest\n\n\033[A\033[A\n\033[A\033[A\033[A\033[A\033[A\tTest\n\033[B\nS\177\177\033[B\033[B\nF00\177\177oo\t\n");

        $dialog = new QuestionHelper();
        $dialog->setInputStream($inputStream);
        $helperSet = new HelperSet(array(new FormatterHelper()));
        $dialog->setHelperSet($helperSet);

        $question = new Question('Please select a bundle', 'FrameworkBundle');
        $question->setAutocompleterValues(array('AcmeDemoBundle', 'AsseticBundle', 'SecurityBundle', 'FooBundle'));

        $this->assertEquals('AcmeDemoBundle', $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $this->assertEquals('AsseticBundleTest', $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $this->assertEquals('FrameworkBundle', $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $this->assertEquals('SecurityBundle', $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $this->assertEquals('FooBundleTest', $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $this->assertEquals('AcmeDemoBundle', $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $this->assertEquals('AsseticBundle', $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $this->assertEquals('FooBundle', $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
    }

    public function testAskHiddenResponse()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test is not supported on Windows');
        }

        $dialog = new QuestionHelper();
        $dialog->setInputStream($this->getInputStream("8AM\n"));

        $question = new Question('What time is it?');
        $question->setHidden(true);

        $this->assertEquals('8AM', $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
    }

    public function testAskConfirmation()
    {
        $dialog = new QuestionHelper();

        $dialog->setInputStream($this->getInputStream("\n\n"));
        $question = new ConfirmationQuestion('Do you like French fries?');
        $this->assertTrue($dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $question = new ConfirmationQuestion('Do you like French fries?', false);
        $this->assertFalse($dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));

        $dialog->setInputStream($this->getInputStream("y\nyes\n"));
        $question = new ConfirmationQuestion('Do you like French fries?', false);
        $this->assertTrue($dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $question = new ConfirmationQuestion('Do you like French fries?', false);
        $this->assertTrue($dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));

        $dialog->setInputStream($this->getInputStream("n\nno\n"));
        $question = new ConfirmationQuestion('Do you like French fries?', true);
        $this->assertFalse($dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $question = new ConfirmationQuestion('Do you like French fries?', true);
        $this->assertFalse($dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
    }

    public function testAskAndValidate()
    {
        $dialog = new QuestionHelper();
        $helperSet = new HelperSet(array(new FormatterHelper()));
        $dialog->setHelperSet($helperSet);

        $error = 'This is not a color!';
        $validator = function ($color) use ($error) {
            if (!in_array($color, array('white', 'black'))) {
                throw new \InvalidArgumentException($error);
            }

            return $color;
        };

        $question = new Question('What color was the white horse of Henry IV?', 'white');
        $question->setValidator($validator);
        $question->setMaxAttempts(2);

        $dialog->setInputStream($this->getInputStream("\nblack\n"));
        $this->assertEquals('white', $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));
        $this->assertEquals('black', $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question));

        $dialog->setInputStream($this->getInputStream("green\nyellow\norange\n"));
        try {
            $dialog->ask($this->createInputInterfaceMock(), $this->createOutputInterface(), $question);
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals($error, $e->getMessage());
        }
    }

    public function testNoInteraction()
    {
        $dialog = new QuestionHelper();
        $question = new Question('Do you have a job?', 'not yet');
        $this->assertEquals('not yet', $dialog->ask($this->createInputInterfaceMock(false), $this->createOutputInterface(), $question));
    }

    protected function getInputStream($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fwrite($stream, $input);
        rewind($stream);

        return $stream;
    }

    protected function createOutputInterface()
    {
        return new StreamOutput(fopen('php://memory', 'r+', false));
    }

    protected function createInputInterfaceMock($interactive = true)
    {
        $mock = $this->getMock('Symfony\Component\Console\Input\InputInterface');
        $mock->expects($this->any())
            ->method('isInteractive')
            ->will($this->returnValue($interactive));

        return $mock;
    }

    private function hasSttyAvailable()
    {
        exec('stty 2>&1', $output, $exitcode);

        return $exitcode === 0;
    }
}
