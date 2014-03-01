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
    private $stepWidth;
    private $percent;
    private $lastMessagesLength;
    private $barCharOriginal;

    static private $formatters;

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

        if (!self::$formatters) {
            self::$formatters = self::initPlaceholderFormatters();
        }
    }

    /**
     * Sets a placeholder formatter for a given name.
     *
     * This method also allow you to override an existing placeholder.
     *
     * @param string   $name     The placeholder name (including the delimiter char like %)
     * @param callable $callable A PHP callable
     */
    public static function setPlaceholderFormatter($name, $callable)
    {
        if (!self::$formatters) {
            self::$formatters = self::initPlaceholderFormatters();
        }

        self::$formatters[$name] = $callable;
    }

    /**
     * Gets the progress bar start time.
     *
     * @return int The progress bar start time
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * Gets the progress bar maximal steps.
     *
     * @return int The progress bar max steps
     */
    public function getMaxSteps()
    {
        return $this->max;
    }

    /**
     * Gets the progress bar step.
     *
     * @return int The progress bar step
     */
    public function getStep()
    {
        return $this->step;
    }

    /**
     * Gets the progress bar step width.
     *
     * @return int The progress bar step width
     */
    public function getStepWidth()
    {
        return $this->stepWidth;
    }

    /**
     * Gets the current progress bar percent.
     *
     * @return int The current progress bar percent
     */
    public function getProgressPercent()
    {
        return $this->percent;
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
     * Gets the progress bar width.
     *
     * @return int The progress bar size
     */
    public function getBarWidth()
    {
        return $this->barWidth;
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
     * Gets the bar character.
     *
     * @return string A character
     */
    public function getBarCharacter()
    {
        return $this->barChar;
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
     * Gets the empty bar character.
     *
     * @return string A character
     */
    public function getEmptyBarCharacter()
    {
        return $this->emptyBarChar;
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
     * Gets the progress bar character.
     *
     * @return string A character
     */
    public function getProgressCharacter()
    {
        return $this->progressChar;
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

        $regex = implode('|', array_keys(self::$formatters));
        $self = $this;
        $this->overwrite(preg_replace_callback("{($regex)}", function ($matches) use ($self) {
            return call_user_func(self::$formatters[$matches[1]], $self);
        }, $this->format));
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

    static private function initPlaceholderFormatters()
    {
        return array(
            '%bar%' => function (ProgressBar $bar) {
                $completeBars = floor($bar->getMaxSteps() > 0 ? $bar->getProgressPercent() * $bar->getBarWidth() : $bar->getStep() % $bar->getBarWidth());
                $emptyBars = $bar->getBarWidth() - $completeBars - Helper::strlen($bar->getProgressCharacter());
                $display = str_repeat($bar->getBarCharacter(), $completeBars);
                if ($completeBars < $bar->getBarWidth()) {
                    $display .= $bar->getProgressCharacter().str_repeat($bar->getEmptyBarCharacter(), $emptyBars);
                }

                return $display;
            },
            '%elapsed%' => function (ProgressBar $bar) {
                return str_pad(Helper::formatTime(time() - $bar->getStartTime()), 6, ' ', STR_PAD_LEFT);
            },
            '%current%' => function (ProgressBar $bar) {
                return str_pad($bar->getStep(), $bar->getStepWidth(), ' ', STR_PAD_LEFT);
            },
            '%max%' => function (ProgressBar $bar) {
                return $bar->getMaxSteps();
            },
            '%percent%' => function (ProgressBar $bar) {
                return str_pad(floor($bar->getProgressPercent() * 100), 3, ' ', STR_PAD_LEFT);
            },
        );
    }
}
