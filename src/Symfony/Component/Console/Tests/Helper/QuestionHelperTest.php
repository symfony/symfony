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

class QuestionHelperTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Symfony\Component\Console\Helper\QuestionHelper */
    private $questionHelper;
    private $fakeStream;
    /** @var \Symfony\Component\Console\Output\StreamOutput */
    private $output;
    /** @var \Symfony\Component\Console\Input\StringInput */
    private $input;

    public static function setUpBeforeClass()
    {
        FakeStream::register();
    }

    protected function setUp()
    {
        $this->fakeStream = fopen('fake://test', 'r+');
        $this->questionHelper = new QuestionHelper();
        $this->questionHelper->setInputStream($this->fakeStream);
        $this->output = new StreamOutput(fopen('php://memory', 'r+', false));
        $this->input = $this->createInputInterfaceMock();
    }

    public function testAskChoice()
    {
        $helperSet = new HelperSet(array(new FormatterHelper()));
        $this->questionHelper->setHelperSet($helperSet);

        $heroes = array('Superman', 'Batman', 'Spiderman');

        $keys = array(
            "\n",        // <NEWLINE>
            "1\n",       // 1<NEWLINE>
            "  1  \n",   // <SPACE><SPACE>1<SPACE><SPACE><NEWLINE>
            "Fabien\n",  // Fabien<NEWLINE>
            "1\n",       // 1<NEWLINE>
            "Fabien\n",  // Fabien<NEWLINE>
            "1\n",       // 1<NEWLINE>
            "0,2\n",     // 0,2<NEWLINE>
            " 0 , 2  \n",// <SPACE>0<SPACE>,<SPACE>2<SPACE><SPACE><NEWLINE>
            "\n",        // <NEWLINE>
            "\n"         // <NEWLINE>
        );
        fwrite($this->fakeStream, implode(';', $keys));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '2');
        // first answer is an empty answer, we're supposed to receive the default value
        $this->assertEquals('Spiderman', $this->questionHelper->ask($this->input, $this->output, $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes);
        $this->assertEquals('Batman', $this->questionHelper->ask($this->input, $this->output, $question));
        $this->assertEquals('Batman', $this->questionHelper->ask($this->input, $this->output, $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes);
        $question->setErrorMessage('Input "%s" is not a superhero!');
        $this->assertEquals('Batman', $this->questionHelper->ask($this->input, $output = $this->output, $question));

        rewind($output->getStream());
        $stream = stream_get_contents($output->getStream());
        $this->assertContains('Input "Fabien" is not a superhero!', $stream);

        try {
            $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '1');
            $question->setMaxAttempts(1);
            $this->questionHelper->ask($this->input, $output = $this->output, $question);
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('Value "Fabien" is invalid', $e->getMessage());
        }

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, null);
        $question->setMultiselect(true);

        $this->assertEquals(array('Batman'), $this->questionHelper->ask($this->input, $this->output, $question));
        $this->assertEquals(array('Superman', 'Spiderman'), $this->questionHelper->ask($this->input, $this->output, $question));
        $this->assertEquals(array('Superman', 'Spiderman'), $this->questionHelper->ask($this->input, $this->output, $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, '0,1');
        $question->setMultiselect(true);

        $this->assertEquals(array('Superman', 'Batman'), $this->questionHelper->ask($this->input, $this->output, $question));

        $question = new ChoiceQuestion('What is your favorite superhero?', $heroes, ' 0 , 1 ');
        $question->setMultiselect(true);

        $this->assertEquals(array('Superman', 'Batman'), $this->questionHelper->ask($this->input, $this->output, $question));
    }

    public function testAsk()
    {
        fwrite($this->fakeStream, "\n;8AM\n");

        $question = new Question('What time is it?', '2PM');
        $this->assertEquals('2PM', $this->questionHelper->ask($this->input, $this->output, $question));

        $question = new Question('What time is it?', '2PM');
        rewind($this->output->getStream());
        $this->assertEquals('8AM', $this->questionHelper->ask($this->input, $this->output, $question));

        rewind($this->output->getStream());
        $this->assertContains('What time is it?', stream_get_contents($this->output->getStream()));
    }

    public function testAskWithAutocomplete()
    {
        if (!$this->hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test autocomplete functionality');
        }

        $keys = array(
            "Acm\t\n",                               // Acm<TAB><NEWLINE>
            "Ac\177s\tTest\n",                       // Ac<BACKSPACE><BACKSPACE>s<TAB>Test<NEWLINE>
            "\n",                                    // <NEWLINE>
            "\033[A\033[A\t\n",                      // <UP ARROW><UP ARROW><TAB><NEWLINE>
            "\033[A\033[A\033[A\033[A\033[A\tTest\n",// <UP ARROW><UP ARROW><UP ARROW><UP ARROW><UP ARROW><TAB>Test<NEWLINE>
            "\033[B\t\n",                            // <DOWN ARROW><TAB><NEWLINE>
            "S\177\177\033[B\033[B\t\n",             // S<BACKSPACE><BACKSPACE><DOWN ARROW><DOWN ARROW><TAB><NEWLINE>
            "F00\177\177oo\t\n"                      // F00<BACKSPACE><BACKSPACE>oo<TAB><NEWLINE>
        );
        fwrite($this->fakeStream, implode(';', $keys), 1024);

        $helperSet = new HelperSet(array(new FormatterHelper()));
        $this->questionHelper->setHelperSet($helperSet);

        $question = new Question('Please select a bundle', 'FrameworkBundle');
        $question->setAutocompleterValues(array('AcmeDemoBundle', 'AsseticBundle', 'SecurityBundle', 'FooBundle'));

        $this->assertEquals('AcmeDemoBundle', $this->questionHelper->ask($this->input, $this->output, $question));
        $this->assertEquals('AsseticBundleTest', $this->questionHelper->ask($this->input, $this->output, $question));
        $this->assertEquals('FrameworkBundle', $this->questionHelper->ask($this->input, $this->output, $question));
        $this->assertEquals('SecurityBundle', $this->questionHelper->ask($this->input, $this->output, $question));
        $this->assertEquals('FooBundleTest', $this->questionHelper->ask($this->input, $this->output, $question));
        $this->assertEquals('AcmeDemoBundle', $this->questionHelper->ask($this->input, $this->output, $question));
        $this->assertEquals('AsseticBundle', $this->questionHelper->ask($this->input, $this->output, $question));
        $this->assertEquals('FooBundle', $this->questionHelper->ask($this->input, $this->output, $question));
    }

    /**
     * @group tty
     */
    public function testAskHiddenResponse()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('This test is not supported on Windows');
        }
        if (!$this->hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to test hidden response');
        }

        fwrite($this->fakeStream, "8AM\n");

        $question = new Question('What time is it?');
        $question->setHidden(true);

        $this->assertEquals('8AM', $this->questionHelper->ask($this->input, $this->output, $question));
    }

    public function testAskConfirmation()
    {
        fwrite($this->fakeStream, "\n;\n");
        $question = new ConfirmationQuestion('Do you like French fries?');
        $this->assertTrue($this->questionHelper->ask($this->input, $this->output, $question));
        $question = new ConfirmationQuestion('Do you like French fries?', false);
        $this->assertFalse($this->questionHelper->ask($this->input, $this->output, $question));

        fwrite($this->fakeStream, "y\n;yes\n");
        $question = new ConfirmationQuestion('Do you like French fries?', false);
        $this->assertTrue($this->questionHelper->ask($this->input, $this->output, $question));
        $question = new ConfirmationQuestion('Do you like French fries?', false);
        $this->assertTrue($this->questionHelper->ask($this->input, $this->output, $question));

        fwrite($this->fakeStream, "n\n;no\n");
        $question = new ConfirmationQuestion('Do you like French fries?', true);
        $this->assertFalse($this->questionHelper->ask($this->input, $this->output, $question));
        $question = new ConfirmationQuestion('Do you like French fries?', true);
        $this->assertFalse($this->questionHelper->ask($this->input, $this->output, $question));
    }

    public function testAskAndValidate()
    {
        $helperSet = new HelperSet(array(new FormatterHelper()));
        $this->questionHelper->setHelperSet($helperSet);

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

        fwrite($this->fakeStream, "\n;black\n");
        $this->assertEquals('white', $this->questionHelper->ask($this->input, $this->output, $question));
        $this->assertEquals('black', $this->questionHelper->ask($this->input, $this->output, $question));

        fwrite($this->fakeStream, "green\n;yellow\n;orange\n");
        try {
            $this->assertEquals('white', $this->questionHelper->ask($this->input, $this->output, $question));
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals($error, $e->getMessage());
        }
    }

    public function testNoInteraction()
    {
        $question = new Question('Do you have a job?', 'not yet');
        $this->assertEquals('not yet', $this->questionHelper->ask($this->createInputInterfaceMock(false), $this->output, $question));
    }

    protected function getInputStream($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fputs($stream, $input);
        rewind($stream);

        return $stream;
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
