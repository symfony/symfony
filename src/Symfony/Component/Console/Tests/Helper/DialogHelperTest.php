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

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Output\StreamOutput;

class DialogHelperTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Symfony\Component\Console\Helper\DialogHelper */
    private $dialog;
    private $fakeStream;
    /** @var \Symfony\Component\Console\Output\StreamOutput */
    private $output;

    public static function setUpBeforeClass()
    {
        FakeStream::register();
    }

    public static function tearDownAfterClass()
    {
        FakeStream::unregister();
    }

    protected function setUp()
    {
        $this->fakeStream = fopen('fake://test', 'r+');
        $this->dialog = new DialogHelper();
        $this->dialog->setInputStream($this->fakeStream);
        $this->output = new StreamOutput(fopen('php://memory', 'r+', false));
    }

    public function testSelect()
    {
        $helperSet = new HelperSet(array(new FormatterHelper()));
        $this->dialog->setHelperSet($helperSet);

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
        $this->assertEquals('2', $this->dialog->select($this->output, 'What is your favorite superhero?', $heroes, '2'));
        $this->assertEquals('1', $this->dialog->select($this->output, 'What is your favorite superhero?', $heroes));
        $this->assertEquals('1', $this->dialog->select($this->output, 'What is your favorite superhero?', $heroes));
        $this->assertEquals('1', $this->dialog->select($this->output, 'What is your favorite superhero?', $heroes, null, false, 'Input "%s" is not a superhero!', false));

        rewind($this->output->getStream());
        $this->assertContains('Input "Fabien" is not a superhero!', stream_get_contents($this->output->getStream()));

        try {
            $this->assertEquals('1', $this->dialog->select($this->output, 'What is your favorite superhero?', $heroes, null, 1));
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('Value "Fabien" is invalid', $e->getMessage());
        }

        $this->assertEquals(array('1'), $this->dialog->select($this->output, 'What is your favorite superhero?', $heroes, null, false, 'Input "%s" is not a superhero!', true));
        $this->assertEquals(array('0', '2'), $this->dialog->select($this->output, 'What is your favorite superhero?', $heroes, null, false, 'Input "%s" is not a superhero!', true));
        $this->assertEquals(array('0', '2'), $this->dialog->select($this->output, 'What is your favorite superhero?', $heroes, null, false, 'Input "%s" is not a superhero!', true));
        $this->assertEquals(array('0', '1'), $this->dialog->select($this->output, 'What is your favorite superhero?', $heroes, '0,1', false, 'Input "%s" is not a superhero!', true));
        $this->assertEquals(array('0', '1'), $this->dialog->select($this->output, 'What is your favorite superhero?', $heroes, ' 0 , 1 ', false, 'Input "%s" is not a superhero!', true));
    }

    public function testAsk()
    {
        fwrite($this->fakeStream, "\n;8AM\n");

        $this->assertEquals('2PM', $this->dialog->ask($this->output, 'What time is it?', '2PM'));
        $this->assertEquals('8AM', $this->dialog->ask($this->output, 'What time is it?', '2PM'));

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

        $bundles = array('AcmeDemoBundle', 'AsseticBundle', 'SecurityBundle', 'FooBundle');

        $this->assertEquals('AcmeDemoBundle', $this->dialog->ask($this->output, 'Please select a bundle', 'FrameworkBundle', $bundles));
        $this->assertEquals('AsseticBundleTest', $this->dialog->ask($this->output, 'Please select a bundle', 'FrameworkBundle', $bundles));
        $this->assertEquals('FrameworkBundle', $this->dialog->ask($this->output, 'Please select a bundle', 'FrameworkBundle', $bundles));
        $this->assertEquals('SecurityBundle', $this->dialog->ask($this->output, 'Please select a bundle', 'FrameworkBundle', $bundles));
        $this->assertEquals('FooBundleTest', $this->dialog->ask($this->output, 'Please select a bundle', 'FrameworkBundle', $bundles));
        $this->assertEquals('AcmeDemoBundle', $this->dialog->ask($this->output, 'Please select a bundle', 'FrameworkBundle', $bundles));
        $this->assertEquals('AsseticBundle', $this->dialog->ask($this->output, 'Please select a bundle', 'FrameworkBundle', $bundles));
        $this->assertEquals('FooBundle', $this->dialog->ask($this->output, 'Please select a bundle', 'FrameworkBundle', $bundles));
    }

    /**
     * @group tty
     */
    public function testAskHiddenResponse()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('This test is not supported on Windows');
        }

        fwrite($this->fakeStream, "8AM\n");

        $this->assertEquals('8AM', $this->dialog->askHiddenResponse($this->output, 'What time is it?'));
    }

    public function testAskConfirmation()
    {
        fwrite($this->fakeStream, "\n;\n");
        $this->assertTrue($this->dialog->askConfirmation($this->output, 'Do you like French fries?'));
        $this->assertFalse($this->dialog->askConfirmation($this->output, 'Do you like French fries?', false));

        fwrite($this->fakeStream, "y\n;yes\n");
        $this->assertTrue($this->dialog->askConfirmation($this->output, 'Do you like French fries?', false));
        $this->assertTrue($this->dialog->askConfirmation($this->output, 'Do you like French fries?', false));

        fwrite($this->fakeStream, "n\n;no\n");
        $this->assertFalse($this->dialog->askConfirmation($this->output, 'Do you like French fries?', true));
        $this->assertFalse($this->dialog->askConfirmation($this->output, 'Do you like French fries?', true));
    }

    public function testAskAndValidate()
    {
        $helperSet = new HelperSet(array(new FormatterHelper()));
        $this->dialog->setHelperSet($helperSet);

        $question ='What color was the white horse of Henry IV?';
        $error = 'This is not a color!';
        $validator = function ($color) use ($error) {
            if (!in_array($color, array('white', 'black'))) {
                throw new \InvalidArgumentException($error);
            }

            return $color;
        };

        fwrite($this->fakeStream, "\n;black\n");
        $this->assertEquals('white', $this->dialog->askAndValidate($this->output, $question, $validator, 2, 'white'));
        $this->assertEquals('black', $this->dialog->askAndValidate($this->output, $question, $validator, 2, 'white'));

        fwrite($this->fakeStream, "green\n;yellow\n;orange\n");
        try {
            $this->assertEquals('white', $this->dialog->askAndValidate($this->output, $question, $validator, 2, 'white'));
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals($error, $e->getMessage());
        }
    }

    public function testNoInteraction()
    {
        $input = new ArrayInput(array());
        $input->setInteractive(false);

        $this->dialog->setInput($input);

        $this->assertEquals('not yet', $this->dialog->ask($this->output, 'Do you have a job?', 'not yet'));
    }

    private function hasSttyAvailable()
    {
        exec('stty 2>&1', $output, $exitcode);

        return $exitcode === 0;
    }
}
