<?php

namespace Symfony\Component\Console\Tests\Helper;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * @group tty
 */
class SymfonyQuestionHelperTest extends AbstractQuestionHelperTest
{
    public function testAskChoice()
    {
        $questionHelper = new SymfonyQuestionHelper();

        $helperSet = new HelperSet([new FormatterHelper()]);
        $questionHelper->setHelperSet($helperSet);

        $heroes = ['Superman', 'Batman', 'Spiderman'];

        $inputStream = $this->getInputStream("\n1\n  1  \nFabien\n1\nFabien\n1\n0,2\n 0 , 2  \n\n\n");

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '2');
        $question->setMaxAttempts(1);
        // first answer is an empty answer, we're supposed to receive the default value
        $this->assertEquals('Spiderman', $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question));
        $this->assertOutputContains('What is your favorite superhero? [Spiderman]', $output);

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes);
        $question->setMaxAttempts(1);
        $this->assertEquals('Batman', $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));
        $this->assertEquals('Batman', $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes);
        $question->setErrorMessage('Input "%s" is not a superhero!');
        $question->setMaxAttempts(2);
        $this->assertEquals('Batman', $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question));
        $this->assertOutputContains('Input "Fabien" is not a superhero!', $output);

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

        $this->assertEquals(['Superman', 'Batman'], $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question));
        $this->assertOutputContains('What is your favorite superhero? [Superman, Batman]', $output);

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, ' 0 , 1 ');
        $question->setMaxAttempts(1);
        $question->setMultiselect(true);

        $this->assertEquals(['Superman', 'Batman'], $questionHelper->ask($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question));
        $this->assertOutputContains('What is your favorite superhero? [Superman, Batman]', $output);
    }

    public function testAskChoiceWithChoiceValueAsDefault()
    {
        $questionHelper = new SymfonyQuestionHelper();
        $helperSet = new HelperSet([new FormatterHelper()]);
        $questionHelper->setHelperSet($helperSet);
        $question = new ChoiceQuestion('What is your favorite superhero?', ['Superman', 'Batman', 'Spiderman'], 'Batman');
        $question->setMaxAttempts(1);

        $this->assertSame('Batman', $questionHelper->ask($this->createStreamableInputInterfaceMock($this->getInputStream("Batman\n")), $output = $this->createOutputInterface(), $question));
        $this->assertOutputContains('What is your favorite superhero? [Batman]', $output);
    }

    public function testAskReturnsNullIfValidatorAllowsIt()
    {
        $questionHelper = new SymfonyQuestionHelper();
        $question = new Question('What is your favorite superhero?');
        $question->setValidator(function ($value) { return $value; });
        $input = $this->createStreamableInputInterfaceMock($this->getInputStream("\n"));
        $this->assertNull($questionHelper->ask($input, $this->createOutputInterface(), $question));
    }

    public function testAskEscapeDefaultValue()
    {
        $helper = new SymfonyQuestionHelper();
        $input = $this->createStreamableInputInterfaceMock($this->getInputStream('\\'));
        $helper->ask($input, $output = $this->createOutputInterface(), new Question('Can I have a backslash?', '\\'));

        $this->assertOutputContains('Can I have a backslash? [\]', $output);
    }

    public function testAskEscapeAndFormatLabel()
    {
        $helper = new SymfonyQuestionHelper();
        $input = $this->createStreamableInputInterfaceMock($this->getInputStream('Foo\\Bar'));
        $helper->ask($input, $output = $this->createOutputInterface(), new Question('Do you want to use Foo\\Bar <comment>or</comment> Foo\\Baz?', 'Foo\\Baz'));

        $this->assertOutputContains('Do you want to use Foo\\Bar or Foo\\Baz? [Foo\\Baz]:', $output);
    }

    public function testForUtf8Keys()
    {
        $question = 'Lorem ipsum?';
        $possibleChoices = [
            'foo' => 'foo',
            'żółw' => 'bar',
            'łabądź' => 'baz',
        ];
        $question_result = ' <info>Lorem ipsum?</info> [<comment>foo</comment>]:';
        $outputShown = [
            '  [<comment>foo   </comment>] foo',
            '  [<comment>żółw  </comment>] bar',
            '  [<comment>łabądź</comment>] baz',
        ];

        $dialog = new SymfonyQuestionHelper();

        $question = new ChoiceQuestion($question, $possibleChoices, 'foo');

        $output = $this->getMockBuilder('\Symfony\Component\Console\Output\OutputInterface')->getMock();
        $output->method('getFormatter')->willReturn(new OutputFormatter());
        $question->setValidator(null);

        $input = $this->createStreamableInputInterfaceMock($this->getInputStream('foo'));
        $result = [];
        $output->method('writeln')->willReturnCallback(function ($params) use (&$result) {
            if (\is_array($params)) {
                $result = array_merge($result, $params);
            } else {
                $result[] = $params;
            }
        });
        $dialog->ask($input, $output, $question);
        $this->assertEquals(array_merge([$question_result], $outputShown), $result);
    }

    public function testAskDefaultPrompt()
    {
        $helper = new SymfonyQuestionHelper();
        $input = $this->createStreamableInputInterfaceMock($this->getInputStream('Foo\\Bar'));
        $helper->ask($input, $output = $this->createOutputInterface(), new Question('Do you want to use Foo\\Bar <comment>or</comment> Foo\\Baz?', 'Foo\\Baz'));

        $this->assertOutputEndsWith("\n > ", $output);
    }

    public function testLabelTrailingBackslash()
    {
        $helper = new SymfonyQuestionHelper();
        $input = $this->createStreamableInputInterfaceMock($this->getInputStream('sure'));
        $helper->ask($input, $output = $this->createOutputInterface(), new Question('Question with a trailing \\'));

        $this->assertOutputContains('Question with a trailing \\', $output);
    }

    public function testAskThrowsExceptionOnMissingInput()
    {
        $this->expectException('Symfony\Component\Console\Exception\RuntimeException');
        $this->expectExceptionMessage('Aborted.');
        $dialog = new SymfonyQuestionHelper();
        $dialog->ask($this->createStreamableInputInterfaceMock($this->getInputStream('')), $this->createOutputInterface(), new Question('What\'s your name?'));
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
        $output = new StreamOutput(fopen('php://memory', 'r+', false));
        $output->setDecorated(false);

        return $output;
    }

    protected function createInputInterfaceMock($interactive = true)
    {
        $mock = $this->getMockBuilder('Symfony\Component\Console\Input\InputInterface')->getMock();
        $mock->expects($this->any())
            ->method('isInteractive')
            ->willReturn($interactive);

        return $mock;
    }

    private function assertOutputContains($expected, StreamOutput $output)
    {
        $stream = $this->getOutputString($output);
        $this->assertStringContainsString($expected, $stream);
    }

    private function assertOutputEndsWith($suffix, StreamOutput $output)
    {
        $stream = $this->getOutputString($output);
        $this->assertStringEndsWith($suffix, $stream);
    }

    private function getOutputString(StreamOutput $output)
    {
        rewind($output->getStream());

        return stream_get_contents($output->getStream()) ?: '';
    }
}
