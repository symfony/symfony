<?php

namespace Symfony\Component\Console\Tests\Question;

use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\SymfonyQuestionPrompt;

/**
 * @group tty
 */
class SymfonyQuestionPromptTest extends AbstractQuestionPromptTest
{
    public function testAskChoice()
    {
        $heroes = ['Superman', 'Batman', 'Spiderman'];
        $inputStream = $this->getInputStream("\n1\n  1  \nFabien\n1\nFabien\n1\n0,2\n 0 , 2  \n\n\n");

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '2');
        $question->setMaxAttempts(1);
        $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question);
        // first answer is an empty answer, we're supposed to receive the default value
        $this->assertEquals('Spiderman', $dialog->ask());
        $this->assertOutputContains('What is your favorite superhero? [Spiderman]', $output);

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes);
        $question->setMaxAttempts(1);
        $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $this->assertEquals('Batman', $dialog->ask());
        $this->assertEquals('Batman', $dialog->ask());

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes);
        $question->setErrorMessage('Input "%s" is not a superhero!');
        $question->setMaxAttempts(2);
        $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question);
        $this->assertEquals('Batman', $dialog->ask());
        $this->assertOutputContains('Input "Fabien" is not a superhero!', $output);

        try {
            $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '1');
            $question->setMaxAttempts(1);
            $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question);
            $dialog->ask();
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('Value "Fabien" is invalid', $e->getMessage());
        }

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, null);
        $question->setMaxAttempts(1);
        $question->setMultiselect(true);
        $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($inputStream), $this->createOutputInterface(), $question);
        $this->assertEquals(['Batman'], $dialog->ask());
        $this->assertEquals(['Superman', 'Spiderman'], $dialog->ask());
        $this->assertEquals(['Superman', 'Spiderman'], $dialog->ask());

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '0,1');
        $question->setMaxAttempts(1);
        $question->setMultiselect(true);
        $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question);
        $this->assertEquals(['Superman', 'Batman'], $dialog->ask());
        $this->assertOutputContains('What is your favorite superhero? [Superman, Batman]', $output);

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, ' 0 , 1 ');
        $question->setMaxAttempts(1);
        $question->setMultiselect(true);
        $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question);
        $this->assertEquals(['Superman', 'Batman'], $dialog->ask());
        $this->assertOutputContains('What is your favorite superhero? [Superman, Batman]', $output);
    }

    public function testAskChoiceWithChoiceValueAsDefault()
    {
        $question = new ChoiceQuestion('What is your favorite superhero?', ['Superman', 'Batman', 'Spiderman'], 'Batman');
        $question->setMaxAttempts(1);
        $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($this->getInputStream("Batman\n")), $output = $this->createOutputInterface(), $question);

        $this->assertSame('Batman', $dialog->ask());
        $this->assertOutputContains('What is your favorite superhero? [Batman]', $output);
    }

    public function testAskReturnsNullIfValidatorAllowsIt()
    {
        $question = new Question('What is your favorite superhero?');
        $question->setValidator(function ($value) { return $value; });
        $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($this->getInputStream("\n")), $this->createOutputInterface(), $question);

        $this->assertNull($dialog->ask());
    }

    public function testAskEscapeDefaultValue()
    {
        $question = new Question('Can I have a backslash?', '\\');
        $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($this->getInputStream('\\')), $output = $this->createOutputInterface(), $question);
        $dialog->ask();

        $this->assertOutputContains('Can I have a backslash? [\]', $output);
    }

    public function testAskEscapeAndFormatLabel()
    {
        $inputStream = $this->getInputStream('Foo\\Bar');
        $question = new Question('Do you want to use Foo\\Bar <comment>or</comment> Foo\\Baz\\?', 'Foo\\Baz');
        $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($inputStream), $output = $this->createOutputInterface(), $question);
        $dialog->ask();

        $this->assertOutputContains('Do you want to use Foo\\Bar or Foo\\Baz\\? [Foo\\Baz]:', $output);
    }

    public function testLabelTrailingBackslash()
    {
        $question = new Question('Question with a trailing \\');
        $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($this->getInputStream('sure')), $output = $this->createOutputInterface(), $question);
        $dialog->ask();

        $this->assertOutputContains('Question with a trailing \\', $output);
    }

    /**
     * @expectedException        \Symfony\Component\Console\Exception\RuntimeException
     * @expectedExceptionMessage Aborted.
     */
    public function testAskThrowsExceptionOnMissingInput()
    {
        $question = new Question('What\'s your name?');
        $dialog = new SymfonyQuestionPrompt($this->createStreamableInputInterfaceMock($this->getInputStream('')), $this->createOutputInterface(), $question);

        $dialog->ask();
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
            ->will($this->returnValue($interactive));

        return $mock;
    }

    private function assertOutputContains($expected, StreamOutput $output)
    {
        rewind($output->getStream());
        $stream = stream_get_contents($output->getStream());
        $this->assertContains($expected, $stream);
    }
}
