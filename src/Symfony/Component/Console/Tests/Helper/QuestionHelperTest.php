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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * @group tty
 */
class QuestionHelperTest extends AbstractQuestionHelperTestCase
{
    public function testAskChoice()
    {
        $questionHelper = new QuestionHelper();

        $helperSet = new HelperSet([new FormatterHelper()]);
        $questionHelper->setHelperSet($helperSet);

        $heroes = ['Superman', 'Batman', 'Spiderman'];

        $inputStream = $this->getInputStream("\n1\n  1  \nFabien\n1\nFabien\n1\n0,2\n 0 , 2  \n\n\n");

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '2');
        $question->setMaxAttempts(1);
        // first answer is an empty answer, we're supposed to receive the default value
        $this->assertEquals('Spiderman', $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes);
        $question->setMaxAttempts(1);
        $this->assertEquals('Batman', $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('Batman', $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes);
        $question->setErrorMessage('Input "%s" is not a superhero!');
        $question->setMaxAttempts(2);
        $this->assertEquals('Batman', $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question));

        rewind($output->getStream());
        $stream = stream_get_contents($output->getStream());
        $this->assertStringContainsString('Input "Fabien" is not a superhero!', $stream);

        try {
            $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '1');
            $question->setMaxAttempts(1);
            $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question);
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('Value "Fabien" is invalid', $e->getMessage());
        }

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, null);
        $question->setMaxAttempts(1);
        $question->setMultiselect(true);

        $this->assertEquals(['Batman'], $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals(['Superman', 'Spiderman'], $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals(['Superman', 'Spiderman'], $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '0,1');
        $question->setMaxAttempts(1);
        $question->setMultiselect(true);

        $this->assertEquals(['Superman', 'Batman'], $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, ' 0 , 1 ');
        $question->setMaxAttempts(1);
        $question->setMultiselect(true);

        $this->assertEquals(['Superman', 'Batman'], $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, 0);
        // We are supposed to get the default value since we are not in interactive mode
        $this->assertEquals('Superman', $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream, true), $this->createOutputInterface(), $question));
    }

    public function testAskChoiceNonInteractive()
    {
        $questionHelper = new QuestionHelper();

        $helperSet = new HelperSet([new FormatterHelper()]);
        $questionHelper->setHelperSet($helperSet);
        $inputStream = $this->getInputStream("\n1\n  1  \nFabien\n1\nFabien\n1\n0,2\n 0 , 2  \n\n\n");

        $heroes = ['Superman', 'Batman', 'Spiderman'];

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '0');

        $this->assertSame('Superman', $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, 'Batman');
        $this->assertSame('Batman', $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, null);
        $this->assertNull($questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '0');
        $question->setValidator(null);
        $this->assertSame('Superman', $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question));

        try {
            $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, null);
            $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question);
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Value "" is invalid', $e->getMessage());
        }

        $question = new ChoiceQuestion('Who are your favorite superheros?', $heroes, '0, 1');
        $question->setMultiselect(true);
        $this->assertSame(['Superman', 'Batman'], $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('Who are your favorite superheros?', $heroes, '0, 1');
        $question->setMultiselect(true);
        $question->setValidator(null);
        $this->assertSame(['Superman', 'Batman'], $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('Who are your favorite superheros?', $heroes, '0, Batman');
        $question->setMultiselect(true);
        $this->assertSame(['Superman', 'Batman'], $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('Who are your favorite superheros?', $heroes, null);
        $question->setMultiselect(true);
        $this->assertNull($questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('Who are your favorite superheros?', ['a' => 'Batman', 'b' => 'Superman'], 'a');
        $this->assertSame('a', $questionHelper->ask($this->createStreamableInputInterfaceMock('', false), $this->createOutputInterface(), $question), 'ChoiceQuestion validator returns the key if it\'s a string');

        try {
            $question = new ChoiceQuestion('Who are your favorite superheros?', $heroes, '');
            $question->setMultiselect(true);
            $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream, false), $this->createOutputInterface(), $question);
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Value "" is invalid', $e->getMessage());
        }
    }

    public function testAsk()
    {
        $dialog = new QuestionHelper();

        $inputStream = $this->getInputStream("\n8AM\n");

        $question = new Question('What time is it?', '2PM');
        $this->assertEquals('2PM', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));

        $question = new Question('What time is it?', '2PM');
        $this->assertEquals('8AM', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question));

        rewind($output->getStream());
        $this->assertEquals('What time is it?', stream_get_contents($output->getStream()));
    }

    public function testAskNonTrimmed()
    {
        $dialog = new QuestionHelper();

        $inputStream = $this->getInputStream(' 8AM ');

        $question = new Question('What time is it?', '2PM');
        $question->setTrimmable(false);
        $this->assertEquals(' 8AM ', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question));

        rewind($output->getStream());
        $this->assertEquals('What time is it?', stream_get_contents($output->getStream()));
    }

    public function testAskWithAutocomplete()
    {
        if (!Terminal::hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        // Acm<NEWLINE>
        // Ac<BACKSPACE><BACKSPACE>s<TAB>Test<NEWLINE>
        // <NEWLINE>
        // <UP ARROW><UP ARROW><UP ARROW><NEWLINE>
        // <UP ARROW><UP ARROW><UP ARROW><UP ARROW><UP ARROW><UP ARROW><UP ARROW><TAB>Test<NEWLINE>
        // <DOWN ARROW><NEWLINE>
        // S<BACKSPACE><BACKSPACE><DOWN ARROW><DOWN ARROW><NEWLINE>
        // F00<BACKSPACE><BACKSPACE>oo<TAB><NEWLINE>
        // F⭐<TAB><BACKSPACE><BACKSPACE>⭐<TAB><NEWLINE>
        $inputStream = $this->getInputStream("Acm\nAc\177\177s\tTest\n\n\033[A\033[A\033[A\n\033[A\033[A\033[A\033[A\033[A\033[A\033[A\tTest\n\033[B\nS\177\177\033[B\033[B\nF00\177\177oo\t\nF⭐\t\177\177⭐\t\n");

        $dialog = new QuestionHelper();
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $question = new Question('Please select a bundle', 'FrameworkBundle');
        $question->setAutocompleterValues(['AcmeDemoBundle', 'AsseticBundle', 'SecurityBundle', 'FooBundle', 'F⭐Y']);

        $this->assertEquals('AcmeDemoBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('AsseticBundleTest', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('FrameworkBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('SecurityBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('FooBundleTest', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('AcmeDemoBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('AsseticBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('FooBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('F⭐Y', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
    }

    public function testAskWithAutocompleteTrimmable()
    {
        if (!Terminal::hasSttyAvailable()) {
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
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $question = new Question('Please select a bundle', 'FrameworkBundle');
        $question->setAutocompleterValues(['AcmeDemoBundle ', 'AsseticBundle', ' SecurityBundle ', 'FooBundle']);
        $question->setTrimmable(false);

        $this->assertEquals('AcmeDemoBundle ', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('AsseticBundleTest', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('FrameworkBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals(' SecurityBundle ', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('FooBundleTest', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('AcmeDemoBundle ', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('AsseticBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('FooBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
    }

    public function testAskWithAutocompleteCallback()
    {
        if (!Terminal::hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        // Po<TAB>Cr<TAB>P<DOWN ARROW><DOWN ARROW><NEWLINE>
        $inputStream = $this->getInputStream("Pa\177\177o\tCr\tP\033[A\033[A\n");

        $dialog = new QuestionHelper();
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $question = new Question('What\'s for dinner?');

        // A simple test callback - return an array containing the words the
        // user has already completed, suffixed with all known words.
        //
        // Eg: If the user inputs "Potato C", the return will be:
        //
        //     ["Potato Carrot ", "Potato Creme ", "Potato Curry ", ...]
        //
        // No effort is made to avoid irrelevant suggestions, as this is handled
        // by the autocomplete function.
        $callback = function ($input) {
            $knownWords = ['Carrot', 'Creme', 'Curry', 'Parsnip', 'Pie', 'Potato', 'Tart'];
            $inputWords = explode(' ', $input);
            array_pop($inputWords);
            $suggestionBase = $inputWords ? implode(' ', $inputWords).' ' : '';

            return array_map(
                fn ($word) => $suggestionBase.$word.' ',
                $knownWords
            );
        };

        $question->setAutocompleterCallback($callback);

        $this->assertSame('Potato Creme Pie', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
    }

    public function testAskWithAutocompleteWithNonSequentialKeys()
    {
        if (!Terminal::hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        // <UP ARROW><UP ARROW><NEWLINE><DOWN ARROW><DOWN ARROW><NEWLINE>
        $inputStream = $this->getInputStream("\033[A\033[A\n\033[B\033[B\n");

        $dialog = new QuestionHelper();
        $dialog->setHelperSet(new HelperSet([new FormatterHelper()]));

        $question = new ChoiceQuestion('Please select a bundle', [1 => 'AcmeDemoBundle', 4 => 'AsseticBundle']);
        $question->setMaxAttempts(1);

        $this->assertEquals('AcmeDemoBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('AsseticBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
    }

    public function testAskWithAutocompleteWithExactMatch()
    {
        if (!Terminal::hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        $inputStream = $this->getInputStream("b\n");

        $possibleChoices = [
            'a' => 'berlin',
            'b' => 'copenhagen',
            'c' => 'amsterdam',
        ];

        $dialog = new QuestionHelper();
        $dialog->setHelperSet(new HelperSet([new FormatterHelper()]));

        $question = new ChoiceQuestion('Please select a city', $possibleChoices);
        $question->setMaxAttempts(1);

        $this->assertSame('b', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
    }

    public static function getInputs()
    {
        return [
            ['$'], // 1 byte character
            ['¢'], // 2 bytes character
            ['€'], // 3 bytes character
            ['𐍈'], // 4 bytes character
        ];
    }

    /**
     * @dataProvider getInputs
     */
    public function testAskWithAutocompleteWithMultiByteCharacter($character)
    {
        if (!Terminal::hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        $inputStream = $this->getInputStream("$character\n");

        $possibleChoices = [
            '$' => '1 byte character',
            '¢' => '2 bytes character',
            '€' => '3 bytes character',
            '𐍈' => '4 bytes character',
        ];

        $dialog = new QuestionHelper();
        $dialog->setHelperSet(new HelperSet([new FormatterHelper()]));

        $question = new ChoiceQuestion('Please select a character', $possibleChoices);
        $question->setMaxAttempts(1);

        $this->assertSame($character, $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
    }

    public function testAutocompleteWithTrailingBackslash()
    {
        if (!Terminal::hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        $inputStream = $this->getInputStream('E');

        $dialog = new QuestionHelper();
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $question = new Question('');
        $expectedCompletion = 'ExampleNamespace\\';
        $question->setAutocompleterValues([$expectedCompletion]);

        $output = $this->createOutputInterface();
        $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $output, $question);

        $outputStream = $output->getStream();
        rewind($outputStream);
        $actualOutput = stream_get_contents($outputStream);

        // Shell control (esc) sequences are not so important: we only care that
        // <hl> tag is interpreted correctly and replaced
        $irrelevantEscSequences = [
            "\0337" => '', // Save cursor position
            "\0338" => '', // Restore cursor position
            "\033[K" => '', // Clear line from cursor till the end
        ];

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

        $dialog = new QuestionHelper();

        $question = new Question('What time is it?');
        $question->setHidden(true);

        $this->assertEquals('8AM', $dialog->ask($this->createStreamableInputInterfaceMock($this->getInputStream("8AM\n")), $this->createOutputInterface(), $question));
    }

    public function testAskHiddenResponseNotTrimmed()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test is not supported on Windows');
        }

        $dialog = new QuestionHelper();

        $question = new Question('What time is it?');
        $question->setHidden(true);
        $question->setTrimmable(false);

        $this->assertEquals(' 8AM'.\PHP_EOL, $dialog->ask($this->createStreamableInputInterfaceMock($this->getInputStream(' 8AM'.\PHP_EOL)), $this->createOutputInterface(), $question));
    }

    public function testAskMultilineResponseWithEOF()
    {
        $essay = <<<'EOD'
Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque pretium lectus quis suscipit porttitor. Sed pretium bibendum vestibulum.

Etiam accumsan, justo vitae imperdiet aliquet, neque est sagittis mauris, sed interdum massa leo id leo.

Aliquam rhoncus, libero ac blandit convallis, est sapien hendrerit nulla, vitae aliquet tellus orci a odio. Aliquam gravida ante sit amet massa lacinia, ut condimentum purus venenatis.

Vivamus et erat dictum, euismod neque in, laoreet odio. Aenean vitae tellus at leo vestibulum auctor id eget urna.
EOD;

        $response = $this->getInputStream($essay);

        $dialog = new QuestionHelper();

        $question = new Question('Write an essay');
        $question->setMultiline(true);

        $this->assertSame($essay, $dialog->ask($this->createStreamableInputInterfaceMock($response), $this->createOutputInterface(), $question));
    }

    public function testAskMultilineResponseWithSingleNewline()
    {
        $response = $this->getInputStream(\PHP_EOL);

        $dialog = new QuestionHelper();

        $question = new Question('Write an essay');
        $question->setMultiline(true);

        $this->assertNull($dialog->ask($this->createStreamableInputInterfaceMock($response), $this->createOutputInterface(), $question));
    }

    public function testAskMultilineResponseWithDataAfterNewline()
    {
        $response = $this->getInputStream(\PHP_EOL.'this is text');

        $dialog = new QuestionHelper();

        $question = new Question('Write an essay');
        $question->setMultiline(true);

        $this->assertNull($dialog->ask($this->createStreamableInputInterfaceMock($response), $this->createOutputInterface(), $question));
    }

    public function testAskMultilineResponseWithMultipleNewlinesAtEnd()
    {
        $typedText = 'This is a body'.\PHP_EOL.\PHP_EOL;
        $response = $this->getInputStream($typedText);

        $dialog = new QuestionHelper();

        $question = new Question('Write an essay');
        $question->setMultiline(true);

        $this->assertSame('This is a body', $dialog->ask($this->createStreamableInputInterfaceMock($response), $this->createOutputInterface(), $question));
    }

    public function testAskMultilineResponseWithWithCursorInMiddleOfSeekableInputStream()
    {
        $input = <<<EOD
This
is
some
input
EOD;
        $response = $this->getInputStream($input);
        fseek($response, 8);

        $dialog = new QuestionHelper();

        $question = new Question('Write an essay');
        $question->setMultiline(true);

        $this->assertSame("some\ninput", $dialog->ask($this->createStreamableInputInterfaceMock($response), $this->createOutputInterface(), $question));
        $this->assertSame(8, ftell($response));
    }

    /**
     * @dataProvider getAskConfirmationData
     */
    public function testAskConfirmation($question, $expected, $default = true)
    {
        $dialog = new QuestionHelper();

        $inputStream = $this->getInputStream($question."\n");
        $question = new ConfirmationQuestion('Do you like French fries?', $default);
        $this->assertEquals($expected, $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question), 'confirmation question should '.($expected ? 'pass' : 'cancel'));
    }

    public static function getAskConfirmationData()
    {
        return [
            ['', true],
            ['', false, false],
            ['y', true],
            ['yes', true],
            ['n', false],
            ['no', false],
        ];
    }

    public function testAskConfirmationWithCustomTrueAnswer()
    {
        $dialog = new QuestionHelper();

        $inputStream = $this->getInputStream("j\ny\n");
        $question = new ConfirmationQuestion('Do you like French fries?', false, '/^(j|y)/i');
        $this->assertTrue($dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $question = new ConfirmationQuestion('Do you like French fries?', false, '/^(j|y)/i');
        $this->assertTrue($dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
    }

    public function testAskAndValidate()
    {
        $dialog = new QuestionHelper();
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $error = 'This is not a color!';
        $validator = function ($color) use ($error) {
            if (!\in_array($color, ['white', 'black'])) {
                throw new \InvalidArgumentException($error);
            }

            return $color;
        };

        $question = new Question('What color was the white horse of Henry IV?', 'white');
        $question->setValidator($validator);
        $question->setMaxAttempts(2);

        $inputStream = $this->getInputStream("\nblack\n");
        $this->assertEquals('white', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('black', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));

        try {
            $dialog->ask($this->createStreamableInputInterfaceMock($this->getInputStream("green\nyellow\norange\n")), $this->createOutputInterface(), $question);
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
        $possibleChoices = [
            'My environment 1',
            'My environment 2',
            'My environment 3',
        ];

        $dialog = new QuestionHelper();
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $question = new ChoiceQuestion('Please select the environment to load', $possibleChoices);
        $question->setMaxAttempts(1);
        $answer = $dialog->ask($this->createStreamableInputInterfaceMock($this->getInputStream($providedAnswer."\n")), $this->createOutputInterface(), $question);

        $this->assertSame($expectedValue, $answer);
    }

    public static function simpleAnswerProvider()
    {
        return [
            [0, 'My environment 1'],
            [1, 'My environment 2'],
            [2, 'My environment 3'],
            ['My environment 1', 'My environment 1'],
            ['My environment 2', 'My environment 2'],
            ['My environment 3', 'My environment 3'],
        ];
    }

    /**
     * @dataProvider specialCharacterInMultipleChoice
     */
    public function testSpecialCharacterChoiceFromMultipleChoiceList($providedAnswer, $expectedValue)
    {
        $possibleChoices = [
            '.',
            'src',
        ];

        $dialog = new QuestionHelper();
        $inputStream = $this->getInputStream($providedAnswer."\n");
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $question = new ChoiceQuestion('Please select the directory', $possibleChoices);
        $question->setMaxAttempts(1);
        $question->setMultiselect(true);
        $answer = $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);

        $this->assertSame($expectedValue, $answer);
    }

    public static function specialCharacterInMultipleChoice()
    {
        return [
            ['.', ['.']],
            ['., src', ['.', 'src']],
        ];
    }

    /**
     * @dataProvider answerProvider
     */
    public function testSelectChoiceFromChoiceList($providedAnswer, $expectedValue)
    {
        $possibleChoices = [
            'env_1' => 'My environment 1',
            'env_2' => 'My environment',
            'env_3' => 'My environment',
        ];

        $dialog = new QuestionHelper();
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $question = new ChoiceQuestion('Please select the environment to load', $possibleChoices);
        $question->setMaxAttempts(1);
        $answer = $dialog->ask($this->createStreamableInputInterfaceMock($this->getInputStream($providedAnswer."\n")), $this->createOutputInterface(), $question);

        $this->assertSame($expectedValue, $answer);
    }

    public function testAmbiguousChoiceFromChoicelist()
    {
        $possibleChoices = [
            'env_1' => 'My first environment',
            'env_2' => 'My environment',
            'env_3' => 'My environment',
        ];

        $dialog = new QuestionHelper();
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $question = new ChoiceQuestion('Please select the environment to load', $possibleChoices);
        $question->setMaxAttempts(1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided answer is ambiguous. Value should be one of "env_2" or "env_3".');

        $dialog->ask($this->createStreamableInputInterfaceMock($this->getInputStream("My environment\n")), $this->createOutputInterface(), $question);
    }

    public static function answerProvider(): array
    {
        return [
            ['env_1', 'env_1'],
            ['env_2', 'env_2'],
            ['env_3', 'env_3'],
            ['My environment 1', 'env_1'],
        ];
    }

    public function testNoInteraction()
    {
        $dialog = new QuestionHelper();
        $question = new Question('Do you have a job?', 'not yet');
        $this->assertEquals('not yet', $dialog->ask($this->createStreamableInputInterfaceMock(null, false), $this->createOutputInterface(), $question));
    }

    public function testChoiceOutputFormattingQuestionForUtf8Keys()
    {
        $question = 'Lorem ipsum?';
        $possibleChoices = [
            'foo' => 'foo',
            'żółw' => 'bar',
            'łabądź' => 'baz',
        ];
        $outputShown = [
            $question,
            '  [<info>foo   </info>] foo',
            '  [<info>żółw  </info>] bar',
            '  [<info>łabądź</info>] baz',
        ];
        $output = $this->createMock(OutputInterface::class);
        $output->method('getFormatter')->willReturn(new OutputFormatter());

        $dialog = new QuestionHelper();
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $output->expects($this->once())->method('writeln')->with($this->equalTo($outputShown));

        $question = new ChoiceQuestion($question, $possibleChoices, 'foo');
        $dialog->ask($this->createStreamableInputInterfaceMock($this->getInputStream("\n")), $output, $question);
    }

    public function testAskThrowsExceptionOnMissingInput()
    {
        $this->expectException(MissingInputException::class);
        $this->expectExceptionMessage('Aborted.');
        (new QuestionHelper())->ask($this->createStreamableInputInterfaceMock($this->getInputStream('')), $this->createOutputInterface(), new Question('What\'s your name?'));
    }

    public function testAskThrowsExceptionOnMissingInputForChoiceQuestion()
    {
        $this->expectException(MissingInputException::class);
        $this->expectExceptionMessage('Aborted.');
        (new QuestionHelper())->ask($this->createStreamableInputInterfaceMock($this->getInputStream('')), $this->createOutputInterface(), new ChoiceQuestion('Choice', ['a', 'b']));
    }

    public function testAskThrowsExceptionOnMissingInputWithValidator()
    {
        $dialog = new QuestionHelper();

        $question = new Question('What\'s your name?');
        $question->setValidator(function ($value) {
            if (!$value) {
                throw new \Exception('A value is required.');
            }
        });

        $this->expectException(MissingInputException::class);
        $this->expectExceptionMessage('Aborted.');

        $dialog->ask($this->createStreamableInputInterfaceMock($this->getInputStream('')), $this->createOutputInterface(), $question);
    }

    public function testQuestionValidatorRepeatsThePrompt()
    {
        $tries = 0;
        $application = new Application();
        $application->setAutoExit(false);
        $application->register('question')
            ->setCode(function ($input, $output) use (&$tries) {
                $question = new Question('This is a promptable question');
                $question->setValidator(function ($value) use (&$tries) {
                    ++$tries;
                    if (!$value) {
                        throw new \Exception();
                    }

                    return $value;
                });

                (new QuestionHelper())->ask($input, $output, $question);

                return 0;
            })
        ;

        $tester = new ApplicationTester($application);
        $tester->setInputs(['', 'not-empty']);

        $statusCode = $tester->run(['command' => 'question'], ['interactive' => true]);

        $this->assertSame(2, $tries);
        $this->assertSame($statusCode, 0);
    }

    public function testEmptyChoices()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Choice question must have at least 1 choice available.');
        new ChoiceQuestion('Question', [], 'irrelevant');
    }

    public function testTraversableAutocomplete()
    {
        if (!Terminal::hasSttyAvailable()) {
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
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $question = new Question('Please select a bundle', 'FrameworkBundle');
        $question->setAutocompleterValues(new AutocompleteValues(['irrelevant' => 'AcmeDemoBundle', 'AsseticBundle', 'SecurityBundle', 'FooBundle']));

        $this->assertEquals('AcmeDemoBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('AsseticBundleTest', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('FrameworkBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('SecurityBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('FooBundleTest', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('AcmeDemoBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('AsseticBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('FooBundle', $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
    }

    public function testDisableStty()
    {
        if (!Terminal::hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid');

        QuestionHelper::disableStty();
        $dialog = new QuestionHelper();
        $dialog->setHelperSet(new HelperSet([new FormatterHelper()]));

        $question = new ChoiceQuestion('Please select a bundle', [1 => 'AcmeDemoBundle', 4 => 'AsseticBundle']);
        $question->setMaxAttempts(1);

        // <UP ARROW><UP ARROW><NEWLINE><DOWN ARROW><DOWN ARROW><NEWLINE>
        // Gives `AcmeDemoBundle` with stty
        $inputStream = $this->getInputStream("\033[A\033[A\n\033[B\033[B\n");

        try {
            $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        } finally {
            $reflection = new \ReflectionProperty(QuestionHelper::class, 'stty');
            $reflection->setValue(null, true);
        }
    }

    public function testTraversableMultiselectAutocomplete()
    {
        if (!Terminal::hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        // <NEWLINE>
        // F<TAB><NEWLINE>
        // A<3x UP ARROW><TAB>,F<TAB><NEWLINE>
        // F00<BACKSPACE><BACKSPACE>o<TAB>,A<DOWN ARROW>,<SPACE>SecurityBundle<NEWLINE>
        // Acme<TAB>,<SPACE>As<TAB><29x BACKSPACE>S<TAB><NEWLINE>
        // Ac<TAB>,As<TAB><3x BACKSPACE>d<TAB><NEWLINE>
        $inputStream = $this->getInputStream("\nF\t\nA\033[A\033[A\033[A\t,F\t\nF00\177\177o\t,A\033[B\t, SecurityBundle\nAcme\t, As\t\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177\177S\t\nAc\t,As\t\177\177\177d\t\n");

        $dialog = new QuestionHelper();
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $question = new ChoiceQuestion(
            'Please select a bundle (defaults to AcmeDemoBundle and AsseticBundle)',
            ['AcmeDemoBundle', 'AsseticBundle', 'SecurityBundle', 'FooBundle'],
            '0,1'
        );

        // This tests that autocomplete works for all multiselect choices entered by the user
        $question->setMultiselect(true);

        $this->assertEquals(['AcmeDemoBundle', 'AsseticBundle'], $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals(['FooBundle'], $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals(['AsseticBundle', 'FooBundle'], $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals(['FooBundle', 'AsseticBundle', 'SecurityBundle'], $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals(['SecurityBundle'], $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals(['AcmeDemoBundle', 'AsseticBundle'], $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
    }

    public function testAutocompleteMoveCursorBackwards()
    {
        // F<TAB><BACKSPACE><BACKSPACE><BACKSPACE>
        $inputStream = $this->getInputStream("F\t\177\177\177");

        $dialog = new QuestionHelper();
        $helperSet = new HelperSet([new FormatterHelper()]);
        $dialog->setHelperSet($helperSet);

        $question = new Question('Question?', 'F⭐Y');
        $question->setAutocompleterValues(['F⭐Y']);

        $dialog->ask($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question);

        $stream = $output->getStream();
        rewind($stream);
        $this->assertStringEndsWith("\033[1D\033[K\033[2D\033[K\033[1D\033[K", stream_get_contents($stream));
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
        $mock = $this->createMock(InputInterface::class);
        $mock->expects($this->any())
            ->method('isInteractive')
            ->willReturn($interactive);

        return $mock;
    }
}

class AutocompleteValues implements \IteratorAggregate
{
    private array $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->values);
    }
}
