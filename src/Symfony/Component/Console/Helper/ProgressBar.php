<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The ProgressBar provides helpers to display progress output.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Chris Jones <leeked@gmail.com>
 */
class ProgressBar
{
    const FORMAT_QUIET         = ' %percent%%';
    const FORMAT_NORMAL        = ' %current%/%max% [%bar%] %percent%%';
    const FORMAT_VERBOSE       = ' %current%/%max% [%bar%] %percent%% Elapsed: %elapsed%';
    const FORMAT_QUIET_NOMAX   = ' %current%';
    const FORMAT_NORMAL_NOMAX  = ' %current% [%bar%]';
    const FORMAT_VERBOSE_NOMAX = ' %current% [%bar%] Elapsed: %elapsed%';

    // options
    private $barWidth     = 28;
    private $barChar      = '=';
    private $emptyBarChar = '-';
    private $progressChar = '>';
    private $format       = null;
    private $redrawFreq   = 1;

    /**
     * @var OutputInterface
     */
    private $output;
    private $step;
    private $max;
    private $startTime;
    private $lastMessagesLength;
    private $barCharOriginal;

    /**
     * List of formatting variables
     *
     * @var array
     */
    private $defaultFormatVars = array(
        'current',
        'max',
        'bar',
        'percent',
        'elapsed',
    );

    /**
     * Available formatting variables
     *
     * @var array
     */
    private $formatVars;

    /**
     * Various time formats
     *
     * @var array
     */
    private $timeFormats = array(
        array(0, '???'),
        array(2, '1 sec'),
        array(59, 'secs', 1),
        array(60, '1 min'),
        array(3600, 'mins', 60),
        array(5400, '1 hr'),
        array(86400, 'hrs', 3600),
        array(129600, '1 day'),
        array(604800, 'days', 86400),
    );

    private $stepWidth;
    private $percent;

    /**
     * Constructor.
     *
     * @param OutputInterface $output An OutputInterface instance
     * @param integer         $max    Maximum steps (0 if unknown)
     */
    public function __construct(OutputInterface $output, $max = 0)
    {
        // Disabling output when it does not support ANSI codes as it would result in a broken display anyway.
        $this->output = $output->isDecorated() ? $output : new NullOutput();
        $this->max = (int) $max;
        $this->stepWidth = $this->max > 0 ? Helper::strlen($this->max) : 4;
    }

    /**
     * Sets the progress bar width.
     *
     * @param int $size The progress bar size
     */
    public function setBarWidth($size)
    {
        $this->barWidth = (int) $size;
    }

    /**
     * Sets the bar character.
     *
     * @param string $char A character
     */
    public function setBarCharacter($char)
    {
        $this->barChar = $char;
    }

    /**
     * Sets the empty bar character.
     *
     * @param string $char A character
     */
    public function setEmptyBarCharacter($char)
    {
        $this->emptyBarChar = $char;
    }

    /**
     * Sets the progress bar character.
     *
     * @param string $char A character
     */
    public function setProgressCharacter($char)
    {
        $this->progressChar = $char;
    }

    /**
     * Sets the progress bar format.
     *
     * @param string $format The format
     */
    public function setFormat($format)
    {
        $this->format = $format;
    }

    /**
     * Sets the redraw frequency.
     *
     * @param int $freq The frequency in steps
     */
    public function setRedrawFrequency($freq)
    {
        $this->redrawFreq = (int) $freq;
    }

    /**
     * Starts the progress output.
     */
    public function start()
    {
        $this->startTime = time();
        $this->step = 0;
        $this->percent = 0;
        $this->lastMessagesLength = 0;
        $this->barCharOriginal = '';

        if (null === $this->format) {
            $this->format = $this->determineBestFormat();
        }

        $this->formatVars = array();
        foreach ($this->defaultFormatVars as $var) {
            if (false !== strpos($this->format, "%{$var}%")) {
                $this->formatVars[$var] = true;
            }
        }

        if (!$this->max) {
            $this->barCharOriginal = $this->barChar;
            $this->barChar = $this->emptyBarChar;
        }

        $this->display();
    }

    /**
     * Advances the progress output X steps.
     *
     * @param integer $step Number of steps to advance
     *
     * @throws \LogicException
     */
    public function advance($step = 1)
    {
        $this->setCurrent($this->step + $step);
    }

    /**
     * Sets the current progress.
     *
     * @param integer $step The current progress
     *
     * @throws \LogicException
     */
    public function setCurrent($step)
    {
        if (null === $this->startTime) {
            throw new \LogicException('You must start the progress bar before calling setCurrent().');
        }

        $step = (int) $step;
        if ($step < $this->step) {
            throw new \LogicException('You can\'t regress the progress bar.');
        }

        if ($this->max > 0 && $step > $this->max) {
            throw new \LogicException('You can\'t advance the progress bar past the max value.');
        }

        $prevPeriod = intval($this->step / $this->redrawFreq);
        $currPeriod = intval($step / $this->redrawFreq);
        $this->step = $step;
        $this->percent = $this->max > 0 ? (float) $this->step / $this->max : 0;
        if ($prevPeriod !== $currPeriod || $this->max === $step) {
            $this->display();
        }
    }

    /**
     * Finishes the progress output.
     */
    public function finish()
    {
        if (null === $this->startTime) {
            throw new \LogicException('You must start the progress bar before calling finish().');
        }

        if (!$this->max) {
            $this->barChar = $this->barCharOriginal;
            $this->max = $this->step;
            $this->setCurrent($this->max);
            $this->max = 0;
            $this->barChar = $this->emptyBarChar;
        } else {
            $this->setCurrent($this->max);
        }

        $this->startTime = null;
    }

    /**
     * Outputs the current progress string.
     *
     * @throws \LogicException
     */
    public function display()
    {
        if (null === $this->startTime) {
            throw new \LogicException('You must start the progress bar before calling display().');
        }

        $message = $this->format;
        foreach ($this->generate() as $name => $value) {
            $message = str_replace("%{$name}%", $value, $message);
        }
        $this->overwrite($message);
    }

    /**
     * Removes the progress bar from the current line.
     *
     * This is useful if you wish to write some output
     * while a progress bar is running.
     * Call display() to show the progress bar again.
     */
    public function clear()
    {
        $this->overwrite('');
    }

    /**
     * Generates the array map of format variables to values.
     *
     * @return array Array of format vars and values
     */
    private function generate()
    {
        $vars = array();

        if (isset($this->formatVars['bar'])) {
            $completeBars = floor($this->max > 0 ? $this->percent * $this->barWidth : $this->step % $this->barWidth);
            $emptyBars = $this->barWidth - $completeBars - Helper::strlen($this->progressChar);
            $bar = str_repeat($this->barChar, $completeBars);
            if ($completeBars < $this->barWidth) {
                $bar .= $this->progressChar;
                $bar .= str_repeat($this->emptyBarChar, $emptyBars);
            }

            $vars['bar'] = $bar;
        }

        if (isset($this->formatVars['elapsed'])) {
            $elapsed = time() - $this->startTime;
            $vars['elapsed'] = str_pad($this->humaneTime($elapsed), 6, ' ', STR_PAD_LEFT);
        }

        if (isset($this->formatVars['current'])) {
            $vars['current'] = str_pad($this->step, $this->stepWidth, ' ', STR_PAD_LEFT);
        }

        if (isset($this->formatVars['max'])) {
            $vars['max'] = $this->max;
        }

        if (isset($this->formatVars['percent'])) {
            $vars['percent'] = str_pad(floor($this->percent * 100), 3, ' ', STR_PAD_LEFT);
        }

        return $vars;
    }

    /**
     * Converts seconds into human-readable format.
     *
     * @param integer $secs Number of seconds
     *
     * @return string Time in readable format
     */
    private function humaneTime($secs)
    {
        $text = '';
        foreach ($this->timeFormats as $format) {
            if ($secs < $format[0]) {
                if (count($format) == 2) {
                    $text = $format[1];
                    break;
                } else {
                    $text = ceil($secs / $format[2]).' '.$format[1];
                    break;
                }
            }
        }

        return $text;
    }

    /**
     * Overwrites a previous message to the output.
     *
     * @param string $message The message
     */
    private function overwrite($message)
    {
        $length = Helper::strlen($message);

        // append whitespace to match the last line's length
        if (null !== $this->lastMessagesLength && $this->lastMessagesLength > $length) {
            $message = str_pad($message, $this->lastMessagesLength, "\x20", STR_PAD_RIGHT);
        }

        // carriage return
        $this->output->write("\x0D");
        $this->output->write($message);

        $this->lastMessagesLength = Helper::strlen($message);
    }

    private function determineBestFormat()
    {
        switch ($this->output->getVerbosity()) {
            case OutputInterface::VERBOSITY_QUIET:
                $format = self::FORMAT_QUIET_NOMAX;
                if ($this->max > 0) {
                    $format = self::FORMAT_QUIET;
                }
                break;
            case OutputInterface::VERBOSITY_VERBOSE:
            case OutputInterface::VERBOSITY_VERY_VERBOSE:
            case OutputInterface::VERBOSITY_DEBUG:
                $format = self::FORMAT_VERBOSE_NOMAX;
                if ($this->max > 0) {
                    $format = self::FORMAT_VERBOSE;
                }
                break;
            default:
                $format = self::FORMAT_NORMAL_NOMAX;
                if ($this->max > 0) {
                    $format = self::FORMAT_NORMAL;
                }
                break;
        }

        return $format;
    }
}
