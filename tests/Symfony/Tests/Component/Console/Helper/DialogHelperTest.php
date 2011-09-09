<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Console\Helper;

use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Output\StreamOutput;

class DialogHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testAsk()
    {
        $dialog = new DialogHelper();

        $dialog->setInputStream($this->getInputStream("\n8AM\n"));

        $this->assertEquals('2PM', $dialog->ask($this->getOutputStream(), 'What time is it?', '2PM'));
        $this->assertEquals('8AM', $dialog->ask($output = $this->getOutputStream(), 'What time is it?', '2PM'));

        rewind($output->getStream());
        $this->assertEquals('What time is it?', stream_get_contents($output->getStream()));
    }

    public function testAskConfirmation()
    {
        $dialog = new DialogHelper();

        $dialog->setInputStream($this->getInputStream("\n\n"));
        $this->assertTrue($dialog->askConfirmation($this->getOutputStream(), 'Do you like French fries?'));
        $this->assertFalse($dialog->askConfirmation($this->getOutputStream(), 'Do you like French fries?', false));

        $dialog->setInputStream($this->getInputStream("y\nyes\n"));
        $this->assertTrue($dialog->askConfirmation($this->getOutputStream(), 'Do you like French fries?', false));
        $this->assertTrue($dialog->askConfirmation($this->getOutputStream(), 'Do you like French fries?', false));

        $dialog->setInputStream($this->getInputStream("n\nno\n"));
        $this->assertFalse($dialog->askConfirmation($this->getOutputStream(), 'Do you like French fries?', true));
        $this->assertFalse($dialog->askConfirmation($this->getOutputStream(), 'Do you like French fries?', true));
    }

    public function testAskAndValidate()
    {
        $dialog = new DialogHelper();
        $helperSet = new HelperSet(array(new FormatterHelper()));
        $dialog->setHelperSet($helperSet);

        $question ='What color was the white horse of Henry IV?';
        $error = 'This is not a color!';
        $validator = function ($color) use ($error) {
            if (!in_array($color, array('white', 'black'))) {
                throw new \InvalidArgumentException($error);
            }

            return $color;
        };

        $dialog->setInputStream($this->getInputStream("\nblack\n"));
        $this->assertEquals('white', $dialog->askAndValidate($this->getOutputStream(), $question, $validator, 2, 'white'));
        $this->assertEquals('black', $dialog->askAndValidate($this->getOutputStream(), $question, $validator, 2, 'white'));

        $dialog->setInputStream($this->getInputStream("green\nyellow\norange\n"));
        try {
            $this->assertEquals('white', $dialog->askAndValidate($this->getOutputStream(), $question, $validator, 2, 'white'));
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals($error, $e->getMessage());
        }
    }

    protected function getInputStream($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fputs($stream, $input);
        rewind($stream);

        return $stream;
    }

    protected function getOutputStream()
    {
        return new StreamOutput(fopen('php://memory', 'r+', false));
    }
}
