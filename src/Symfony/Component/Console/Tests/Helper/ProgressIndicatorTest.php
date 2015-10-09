<?php

namespace Symfony\Component\Console\Tests\Helper;

use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Output\StreamOutput;

class ProgressIndicatorTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultIndicator()
    {
        $bar = new ProgressIndicator($output = $this->getOutputStream());
        $bar->start('Starting...');
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->setMessage('Advancing...');
        $bar->advance();
        $bar->finish('Done...');

        rewind($output->getStream());

        $this->assertEquals(
            $this->generateOutput(' - Starting...').
            $this->generateOutput(' \\ Starting...').
            $this->generateOutput(' | Starting...').
            $this->generateOutput(' / Starting...').
            $this->generateOutput(' - Starting...').
            $this->generateOutput(' \\ Starting...').
            $this->generateOutput(' \\ Advancing...').
            $this->generateOutput(' | Advancing...').
            $this->generateOutput(' | Done...     ').
            "\n",
            stream_get_contents($output->getStream())
        );
    }

    public function testNonDecoratedOutput()
    {
        $bar = new ProgressIndicator($output = $this->getOutputStream(false));

        $bar->start('Starting...');
        $bar->advance();
        $bar->advance();
        $bar->setMessage('Midway...');
        $bar->advance();
        $bar->advance();
        $bar->finish('Done...');

        rewind($output->getStream());

        $this->assertEquals(
            ' Starting...'.PHP_EOL.
            ' Midway...  '.PHP_EOL.
            ' Done...    '.PHP_EOL.PHP_EOL,
            stream_get_contents($output->getStream())
        );
    }

    public function testCustomIndicatorValues()
    {
        $bar = new ProgressIndicator($output = $this->getOutputStream());
        $bar->setIndicatorValues(array('a', 'b', 'c'));

        $bar->start('Starting...');
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->advance();

        rewind($output->getStream());

        $this->assertEquals(
            $this->generateOutput(' a Starting...').
            $this->generateOutput(' b Starting...').
            $this->generateOutput(' c Starting...').
            $this->generateOutput(' a Starting...'),
            stream_get_contents($output->getStream())
        );
    }

    /**
     * @dataProvider provideFormat
     */
    public function testFormats($format)
    {
        $bar = new ProgressIndicator($output = $this->getOutputStream());
        $bar->setFormat($format);
        $bar->start('Starting...');
        $bar->advance();

        rewind($output->getStream());

        $this->assertNotEmpty(stream_get_contents($output->getStream()));
    }

    /**
     * Provides each defined format.
     *
     * @return array
     */
    public function provideFormat()
    {
        return array(
            array('normal'),
            array('verbose'),
            array('very_verbose'),
            array('debug'),
        );
    }

    protected function getOutputStream($decorated = true, $verbosity = StreamOutput::VERBOSITY_NORMAL)
    {
        return new StreamOutput(fopen('php://memory', 'r+', false), $verbosity, $decorated);
    }

    protected function generateOutput($expected)
    {
        $count = substr_count($expected, "\n");

        return "\x0D".($count ? sprintf("\033[%dA", $count) : '').$expected;
    }
}
