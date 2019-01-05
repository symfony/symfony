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

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\AskQuestion;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * @group tty
 */
class AskQuestionTest extends AbstractAskQuestionTest
{
    public function testAskChoice()
    {
        $heroes = array('Superman', 'Batman', 'Spiderman');
        $inputStream = $this->getInputStream("\n1\n  1  \nFabien\n1\nFabien\n1\n0,2\n 0 , 2  \n\n\n");

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '2');
        $question->setMaxAttempts(1);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        // first answer is an empty answer, we're supposed to receive the default value
        $this->assertEquals('Spiderman', $dialog->ask());

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes);
        $question->setMaxAttempts(1);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $this->assertEquals('Batman', $dialog->ask());
        $this->assertEquals('Batman', $dialog->ask());

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes);
        $question->setErrorMessage('Input "%s" is not a superhero!');
        $question->setMaxAttempts(2);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question);
        $this->assertEquals('Batman', $dialog->ask());

        rewind($output->getStream());
        $stream = stream_get_contents($output->getStream());
        $this->assertContains('Input "Fabien" is not a superhero!', $stream);

        try {
            $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '1');
            $question->setMaxAttempts(1);
            $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question);
            $dialog->ask();
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('Value "Fabien" is invalid', $e->getMessage());
        }

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, null);
        $question->setMaxAttempts(1);
        $question->setMultiselect(true);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $this->assertEquals(array('Batman'), $dialog->ask());
        $this->assertEquals(array('Superman', 'Spiderman'), $dialog->ask());
        $this->assertEquals(array('Superman', 'Spiderman'), $dialog->ask());

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '0,1');
        $question->setMaxAttempts(1);
        $question->setMultiselect(true);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $this->assertEquals(array('Superman', 'Batman'), $dialog->ask());

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, ' 0 , 1 ');
        $question->setMaxAttempts(1);
        $question->setMultiselect(true);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $this->assertEquals(array('Superman', 'Batman'), $dialog->ask());

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, 0);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream, true), $this->createOutputInterface(), $question);
        // We are supposed to get the default value since we are not in interactive mode
        $this->assertEquals('Superman', $dialog->ask());
    }

    public function testAskChoiceNonInteractive()
    {
        $heroes = array('Superman', 'Batman', 'Spiderman');
        $inputStream = $this->getInputStream("\n1\n  1  \nFabien\n1\nFabien\n1\n0,2\n 0 , 2  \n\n\n");

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '0');
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question);
        $this->assertSame('Superman', $dialog->ask());

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, 'Batman');
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question);
        $this->assertSame('Batman', $dialog->ask());

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, null);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question);
        $this->assertNull($dialog->ask());

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '0');
        $question->setValidator(null);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question);
        $this->assertSame('Superman', $dialog->ask());

        try {
            $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, null);
            $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question);
            $dialog->ask();
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Value "" is invalid', $e->getMessage());
        }

        $question = new ChoiceQuestion('Who are your favorite superheros?', $heroes, '0, 1');
        $question->setMultiselect(true);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question);
        $this->assertSame(array('Superman', 'Batman'), $dialog->ask());

        $question = new ChoiceQuestion('Who are your favorite superheros?', $heroes, '0, 1');
        $question->setMultiselect(true);
        $question->setValidator(null);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question);
        $this->assertSame(array('Superman', 'Batman'), $dialog->ask());

        $question = new ChoiceQuestion('Who are your favorite superheros?', $heroes, '0, Batman');
        $question->setMultiselect(true);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question);
        $this->assertSame(array('Superman', 'Batman'), $dialog->ask());

        $question = new ChoiceQuestion('Who are your favorite superheros?', $heroes, null);
        $question->setMultiselect(true);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question);
        $this->assertNull($dialog->ask());

        try {
            $question = new ChoiceQuestion('Who are your favorite superheros?', $heroes, '');
            $question->setMultiselect(true);
            $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question);
            $dialog->ask();
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Value "" is invalid', $e->getMessage());
        }
    }

    public function testAsk()
    {
        $inputStream = $this->getInputStream("\n8AM\n");

        $question = new Question('What time is it?', '2PM');
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $this->assertEquals('2PM', $dialog->ask());

        $question = new Question('What time is it?', '2PM');
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question);
        $this->assertEquals('8AM', $dialog->ask());

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

        $question = new Question('Please select a bundle', 'FrameworkBundle');
        $question->setAutocompleterValues(array('AcmeDemoBundle', 'AsseticBundle', 'SecurityBundle', 'FooBundle'));
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);

        $this->assertEquals('AcmeDemoBundle', $dialog->ask());
        $this->assertEquals('AsseticBundleTest', $dialog->ask());
        $this->assertEquals('FrameworkBundle', $dialog->ask());
        $this->assertEquals('SecurityBundle', $dialog->ask());
        $this->assertEquals('FooBundleTest', $dialog->ask());
        $this->assertEquals('AcmeDemoBundle', $dialog->ask());
        $this->assertEquals('AsseticBundle', $dialog->ask());
        $this->assertEquals('FooBundle', $dialog->ask());
    }

    public function testAskWithAutocompleteWithNonSequentialKeys()
    {
        if (!$this->hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        // <UP ARROW><UP ARROW><NEWLINE><DOWN ARROW><DOWN ARROW><NEWLINE>
        $inputStream = $this->getInputStream("\033[A\033[A\n\033[B\033[B\n");

        $question = new ChoiceQuestion('Please select a bundle', array(1 => 'AcmeDemoBundle', 4 => 'AsseticBundle'));
        $question->setMaxAttempts(1);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);

        $this->assertEquals('AcmeDemoBundle', $dialog->ask());
        $this->assertEquals('AsseticBundle', $dialog->ask());
    }

    public function testAskWithAutocompleteWithExactMatch()
    {
        if (!$this->hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        $inputStream = $this->getInputStream("b\n");
        $possibleChoices = array(
            'a' => 'berlin',
            'b' => 'copenhagen',
            'c' => 'amsterdam',
        );

        $question = new ChoiceQuestion('Please select a city', $possibleChoices);
        $question->setMaxAttempts(1);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);

        $this->assertSame('b', $dialog->ask());
    }

    public function testAutocompleteWithTrailingBackslash()
    {
        if (!$this->hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        $inputStream = $this->getInputStream('E');

        $question = new Question('');
        $expectedCompletion = 'ExampleNamespace\\';
        $question->setAutocompleterValues(array($expectedCompletion));
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question);

        $dialog->ask();

        $outputStream = $output->getStream();
        rewind($outputStream);
        $actualOutput = stream_get_contents($outputStream);

        // Shell control (esc) sequences are not so important: we only care that
        // <hl> tag is interpreted correctly and replaced
        $irrelevantEscSequences = array(
            "\0337" => '', // Save cursor position
            "\0338" => '', // Restore cursor position
            "\033[K" => '', // Clear line from cursor till the end
        );

        $importantActualOutput = strtr($actualOutput, $irrelevantEscSequences);

        // Remove colors (e.g. "\033[30m", "\033[31;41m")
        $importantActualOutput = preg_replace('/\033\[\d+(;\d+)?m/', '', $importantActualOutput);

        $this->assertEquals($expectedCompletion, $importantActualOutput);
    }

    public function testAskHiddenResponse()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test is not supported on Windows');
        }

        $inputStream = $this->getInputStream("8AM\n");

        $question = new Question('What time is it?');
        $question->setHidden(true);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);

        $this->assertEquals('8AM', $dialog->ask());
    }

    /**
     * @dataProvider getAskConfirmationData
     */
    public function testAskConfirmation($question, $expected, $default = true)
    {
        $inputStream = $this->getInputStream($question."\n");

        $question = new ConfirmationQuestion('Do you like French fries?', $default);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $this->assertEquals($expected, $dialog->ask(), 'confirmation question should '.($expected ? 'pass' : 'cancel'));
    }

    public function getAskConfirmationData()
    {
        return array(
            array('', true),
            array('', false, false),
            array('y', true),
            array('yes', true),
            array('n', false),
            array('no', false),
        );
    }

    public function testAskConfirmationWithCustomTrueAnswer()
    {
        $inputStream = $this->getInputStream("j\ny\n");

        $question = new ConfirmationQuestion('Do you like French fries?', false, '/^(j|y)/i');
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $this->assertTrue($dialog->ask());

        $question = new ConfirmationQuestion('Do you like French fries?', false, '/^(j|y)/i');
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $this->assertTrue($dialog->ask());
    }

    public function testAskAndValidate()
    {
        $error = 'This is not a color!';
        $validator = function ($color) use ($error) {
            if (!\in_array($color, array('white', 'black'))) {
                throw new \InvalidArgumentException($error);
            }

            return $color;
        };

        $question = new Question('What color was the white horse of Henry IV?', 'white');
        $question->setValidator($validator);
        $question->setMaxAttempts(2);

        $inputStream = $this->getInputStream("\nblack\n");
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        // first answer is an empty answer, we're supposed to receive the default value
        $this->assertEquals('white', $dialog->ask());
        $this->assertEquals('black', $dialog->ask());

        try {
            $inputStream = $this->getInputStream("green\nyellow\norange\n");
            $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
            $dialog->ask();
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals($error, $e->getMessage());
        }
    }

    /**
     * @dataProvider simpleAnswerProvider
     */
    public function testSelectChoiceFromSimpleChoices($providedAnswer, $expectedValue)
    {
        $inputStream = $this->getInputStream($providedAnswer."\n");
        $possibleChoices = array(
            'My environment 1',
            'My environment 2',
            'My environment 3',
        );

        $question = new ChoiceQuestion('Please select the environment to load', $possibleChoices);
        $question->setMaxAttempts(1);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $answer = $dialog->ask();

        $this->assertSame($expectedValue, $answer);
    }

    public function simpleAnswerProvider()
    {
        return array(
            array(0, 'My environment 1'),
            array(1, 'My environment 2'),
            array(2, 'My environment 3'),
            array('My environment 1', 'My environment 1'),
            array('My environment 2', 'My environment 2'),
            array('My environment 3', 'My environment 3'),
        );
    }

    /**
     * @dataProvider specialCharacterInMultipleChoice
     */
    public function testSpecialCharacterChoiceFromMultipleChoiceList($providedAnswer, $expectedValue)
    {
        $inputStream = $this->getInputStream($providedAnswer."\n");
        $possibleChoices = array(
            '.',
            'src',
        );

        $question = new ChoiceQuestion('Please select the directory', $possibleChoices);
        $question->setMaxAttempts(1);
        $question->setMultiselect(true);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $answer = $dialog->ask();

        $this->assertSame($expectedValue, $answer);
    }

    public function specialCharacterInMultipleChoice()
    {
        return array(
            array('.', array('.')),
            array('., src', array('.', 'src')),
        );
    }

    /**
     * @dataProvider mixedKeysChoiceListAnswerProvider
     */
    public function testChoiceFromChoicelistWithMixedKeys($providedAnswer, $expectedValue)
    {
        $inputStream = $this->getInputStream($providedAnswer."\n");
        $possibleChoices = array(
            '0' => 'No environment',
            '1' => 'My environment 1',
            'env_2' => 'My environment 2',
            3 => 'My environment 3',
        );

        $question = new ChoiceQuestion('Please select the environment to load', $possibleChoices);
        $question->setMaxAttempts(1);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $answer = $dialog->ask();

        $this->assertSame($expectedValue, $answer);
    }

    public function mixedKeysChoiceListAnswerProvider()
    {
        return array(
            array('0', '0'),
            array('No environment', '0'),
            array('1', '1'),
            array('env_2', 'env_2'),
            array(3, '3'),
            array('My environment 1', '1'),
        );
    }

    /**
     * @dataProvider answerProvider
     */
    public function testSelectChoiceFromChoiceList($providedAnswer, $expectedValue)
    {
        $inputStream = $this->getInputStream($providedAnswer."\n");
        $possibleChoices = array(
            'env_1' => 'My environment 1',
            'env_2' => 'My environment',
            'env_3' => 'My environment',
        );

        $question = new ChoiceQuestion('Please select the environment to load', $possibleChoices);
        $question->setMaxAttempts(1);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $answer = $dialog->ask();

        $this->assertSame($expectedValue, $answer);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage The provided answer is ambiguous. Value should be one of env_2 or env_3.
     */
    public function testAmbiguousChoiceFromChoicelist()
    {
        $inputStream = $this->getInputStream("My environment\n");
        $possibleChoices = array(
            'env_1' => 'My first environment',
            'env_2' => 'My environment',
            'env_3' => 'My environment',
        );

        $question = new ChoiceQuestion('Please select the environment to load', $possibleChoices);
        $question->setMaxAttempts(1);
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);

        $dialog->ask();
    }

    public function answerProvider()
    {
        return array(
            array('env_1', 'env_1'),
            array('env_2', 'env_2'),
            array('env_3', 'env_3'),
            array('My environment 1', 'env_1'),
        );
    }

    public function testNoInteraction()
    {
        $question = new Question('Do you have a job?', 'not yet');
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock(null, false), $this->createOutputInterface(), $question);

        $this->assertEquals('not yet', $dialog->ask());
    }

    /**
     * @requires function mb_strwidth
     */
    public function testChoiceOutputFormattingQuestionForUtf8Keys()
    {
        $question = 'Lorem ipsum?';
        $possibleChoices = array(
            'foo' => 'foo',
            'żółw' => 'bar',
            'łabądź' => 'baz',
        );
        $outputShown = array(
            $question,
            '  [<info>foo   </info>] foo',
            '  [<info>żółw  </info>] bar',
            '  [<info>łabądź</info>] baz',
        );
        $output = $this->getMockBuilder('\Symfony\Component\Console\Output\OutputInterface')->getMock();
        $output->method('getFormatter')->willReturn(new OutputFormatter());

        $output->expects($this->once())->method('writeln')->with($this->equalTo($outputShown));

        $question = new ChoiceQuestion($question, $possibleChoices, 'foo');
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($this->getInputStream("\n")), $output, $question);
        $dialog->ask();
    }

    /**
     * @expectedException        \Symfony\Component\Console\Exception\RuntimeException
     * @expectedExceptionMessage Aborted
     */
    public function testAskThrowsExceptionOnMissingInput()
    {
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($this->getInputStream('')), $this->createOutputInterface(), new Question('What\'s your name?'));
        $dialog->ask();
    }

    /**
     * @expectedException        \Symfony\Component\Console\Exception\RuntimeException
     * @expectedExceptionMessage Aborted
     */
    public function testAskThrowsExceptionOnMissingInputWithValidator()
    {
        $question = new Question('What\'s your name?');
        $question->setValidator(function () {
            if (!$value) {
                throw new \Exception('A value is required.');
            }
        });
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($this->getInputStream('')), $this->createOutputInterface(), $question);

        $dialog->ask();
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Choice question must have at least 1 choice available.
     */
    public function testEmptyChoices()
    {
        new ChoiceQuestion('Question', array(), 'irrelevant');
    }

    public function testTraversableAutocomplete()
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

        $question = new Question('Please select a bundle', 'FrameworkBundle');
        $question->setAutocompleterValues(new AutocompleteValues(array('irrelevant' => 'AcmeDemoBundle', 'AsseticBundle', 'SecurityBundle', 'FooBundle')));
        $dialog = new AskQuestion($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);

        $this->assertEquals('AcmeDemoBundle', $dialog->ask());
        $this->assertEquals('AsseticBundleTest', $dialog->ask());
        $this->assertEquals('FrameworkBundle', $dialog->ask());
        $this->assertEquals('SecurityBundle', $dialog->ask());
        $this->assertEquals('FooBundleTest', $dialog->ask());
        $this->assertEquals('AcmeDemoBundle', $dialog->ask());
        $this->assertEquals('AsseticBundle', $dialog->ask());
        $this->assertEquals('FooBundle', $dialog->ask());
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
        $mock = $this->getMockBuilder('Symfony\Component\Console\Input\InputInterface')->getMock();
        $mock->expects($this->any())
            ->method('isInteractive')
            ->will($this->returnValue($interactive));

        return $mock;
    }

    private function hasSttyAvailable()
    {
        exec('stty 2>&1', $output, $exitcode);

        return 0 === $exitcode;
    }
}

class AutocompleteValues implements \IteratorAggregate
{
    private $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->values);
    }
}
