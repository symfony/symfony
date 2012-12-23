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

use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Output\StreamOutput;

class ProgressHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testAdvance()
    {
        $progress = new ProgressHelper();
        $progress->start($output = $this->getOutputStream());
        $progress->advance();

        rewind($output->getStream());
        $this->assertEquals($this->generateOutput('    1 [->--------------------------]'), stream_get_contents($output->getStream()));
    }

    public function testAdvanceWithStep()
    {
        $progress = new ProgressHelper();
        $progress->start($output = $this->getOutputStream());
        $progress->advance(5);

        rewind($output->getStream());
        $this->assertEquals($this->generateOutput('    5 [----->----------------------]'), stream_get_contents($output->getStream()));
    }

    public function testAdvanceMultipleTimes()
    {
        $progress = new ProgressHelper();
        $progress->start($output = $this->getOutputStream());
        $progress->advance(3);
        $progress->advance(2);

        rewind($output->getStream());
        $this->assertEquals($this->generateOutput('    3 [--->------------------------]').$this->generateOutput('    5 [----->----------------------]'), stream_get_contents($output->getStream()));
    }

    public function testCustomizations()
    {
        $progress = new ProgressHelper();
        $progress->setBarWidth(10);
        $progress->setBarCharacter('_');
        $progress->setEmptyBarCharacter(' ');
        $progress->setProgressCharacter('/');
        $progress->setFormat(' %current%/%max% [%bar%] %percent%%');
        $progress->start($output = $this->getOutputStream(), 10);
        $progress->advance();

        rewind($output->getStream());
        $this->assertEquals($this->generateOutput('  1/10 [_/        ]  10%'), stream_get_contents($output->getStream()));
    }

    public function testPercent()
    {
        $progress = new ProgressHelper();
        $progress->start($output = $this->getOutputStream(), 50);
        $progress->display();
        $progress->advance();
        $progress->advance();

        rewind($output->getStream());
        $this->assertEquals($this->generateOutput('  0/50 [>---------------------------]   0%').$this->generateOutput('  1/50 [>---------------------------]   2%').$this->generateOutput('  2/50 [=>--------------------------]   4%'), stream_get_contents($output->getStream()));
    }

    protected function getOutputStream()
    {
        return new StreamOutput(fopen('php://memory', 'r+', false));
    }

    protected $lastMessagesLength;

    protected function generateOutput($expected)
    {
        $expectedout = $expected;

        if ($this->lastMessagesLength !== null) {
            $expectedout = str_repeat("\x20", $this->lastMessagesLength)."\x0D".$expected;
        }

        $this->lastMessagesLength = strlen($expected);

        return "\x0D".$expectedout;
    }
}
