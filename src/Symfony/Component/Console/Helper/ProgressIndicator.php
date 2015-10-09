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

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class ProgressIndicator
{
    private $output;
    private $startTime;
    private $format;
    private $message = null;
    private $indicatorValues = array('-', '\\', '|', '/');
    private $indicatorCurrent = 0;
    private $indicatorChangeInterval = 100;
    private $indicatorUpdateTime;
    private $lastMessagesLength = 0;

    private static $formatters;
    private static $formats;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
        $this->startTime = time();

        $this->setFormat($this->determineBestFormat());
    }

    /**
     * @param string $format
     */
    public function setFormat($format)
    {
        $this->format = self::getFormatDefinition($format);
    }

    /**
     * @param int $milliseconds
     */
    public function setIndicatorChangeInterval($milliseconds)
    {
        $this->indicatorChangeInterval = $milliseconds;
    }

    /**
     * @param string|null $message
     */
    public function setMessage($message)
    {
        $this->message = $message;

        $this->display();
    }

    /**
     * @return string|null
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param array $values
     */
    public function setIndicatorValues(array $values)
    {
        $values = array_values($values);

        if (empty($values)) {
            throw new \InvalidArgumentException('Must have at least 1 value.');
        }

        $this->indicatorValues = $values;
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
     * @return string
     */
    public function getCurrentValue()
    {
        return $this->indicatorValues[$this->indicatorCurrent % count($this->indicatorValues)];
    }

    public function start($message)
    {
        $this->message = $message;
        $this->startTime = time();
        $this->indicatorUpdateTime = $this->getCurrentTimeInMilliseconds() + $this->indicatorChangeInterval;
        $this->indicatorCurrent = 0;

        $this->display();
    }

    public function advance()
    {
        if (!$this->output->isDecorated()) {
            return;
        }

        $currentTime = $this->getCurrentTimeInMilliseconds();

        if ($currentTime < $this->indicatorUpdateTime) {
            return;
        }

        $this->indicatorUpdateTime = $currentTime + $this->indicatorChangeInterval;
        ++$this->indicatorCurrent;

        $this->display();
    }

    public function finish($message)
    {
        $this->message = $message;
        $this->display();
        $this->output->writeln('');
    }

    /**
     * Gets the format for a given name.
     *
     * @param string $name The format name
     *
     * @return string|null A format string
     */
    public static function getFormatDefinition($name)
    {
        if (!self::$formats) {
            self::$formats = self::initFormats();
        }

        return isset(self::$formats[$name]) ? self::$formats[$name] : null;
    }

    /**
     * Sets a placeholder formatter for a given name.
     *
     * This method also allow you to override an existing placeholder.
     *
     * @param string   $name     The placeholder name (including the delimiter char like %)
     * @param callable $callable A PHP callable
     */
    public static function setPlaceholderFormatterDefinition($name, $callable)
    {
        if (!self::$formatters) {
            self::$formatters = self::initPlaceholderFormatters();
        }

        self::$formatters[$name] = $callable;
    }

    /**
     * Gets the placeholder formatter for a given name.
     *
     * @param string $name The placeholder name (including the delimiter char like %)
     *
     * @return callable|null A PHP callable
     */
    public static function getPlaceholderFormatterDefinition($name)
    {
        if (!self::$formatters) {
            self::$formatters = self::initPlaceholderFormatters();
        }

        return isset(self::$formatters[$name]) ? self::$formatters[$name] : null;
    }

    private function display()
    {
        if (OutputInterface::VERBOSITY_QUIET === $this->output->getVerbosity()) {
            return;
        }

        $self = $this;

        $this->overwrite(preg_replace_callback("{%([a-z\-_]+)(?:\:([^%]+))?%}i", function ($matches) use ($self) {
            if ($formatter = $self::getPlaceholderFormatterDefinition($matches[1])) {
                return call_user_func($formatter, $self);
            }

            return $matches[0];
        }, $this->format));
    }

    private function determineBestFormat()
    {
        switch ($this->output->getVerbosity()) {
            // OutputInterface::VERBOSITY_QUIET: display is disabled anyway
            case OutputInterface::VERBOSITY_VERBOSE:
                return $this->output->isDecorated() ? 'verbose' : 'verbose_no_ansi';
            case OutputInterface::VERBOSITY_VERY_VERBOSE:
            case OutputInterface::VERBOSITY_DEBUG:
                return $this->output->isDecorated() ? 'very_verbose' : 'very_verbose_no_ansi';
            default:
                return $this->output->isDecorated() ? 'normal' : 'normal_no_ansi';
        }
    }

    /**
     * Overwrites a previous message to the output.
     *
     * @param string $message The message
     */
    private function overwrite($message)
    {
        // append whitespace to match the line's length
        if (null !== $this->lastMessagesLength) {
            if ($this->lastMessagesLength > Helper::strlenWithoutDecoration($this->output->getFormatter(), $message)) {
                $message = str_pad($message, $this->lastMessagesLength, "\x20", STR_PAD_RIGHT);
            }
        }

        if ($this->output->isDecorated()) {
            $this->output->write("\x0D");
            $this->output->write($message);
        } else {
            $this->output->writeln($message);
        }

        $this->lastMessagesLength = 0;

        $len = Helper::strlenWithoutDecoration($this->output->getFormatter(), $message);

        if ($len > $this->lastMessagesLength) {
            $this->lastMessagesLength = $len;
        }
    }

    private function getCurrentTimeInMilliseconds()
    {
        return round(microtime(true) * 1000);
    }

    private static function initPlaceholderFormatters()
    {
        return array(
            'indicator' => function (ProgressIndicator $indicator) {
                return $indicator->getCurrentValue();
            },
            'message' => function (ProgressIndicator $indicator) {
                return $indicator->getMessage();
            },
            'elapsed' => function (ProgressIndicator $indicator) {
                return Helper::formatTime(time() - $indicator->getStartTime());
            },
            'memory' => function () {
                return Helper::formatMemory(memory_get_usage(true));
            },
        );
    }

    private static function initFormats()
    {
        return array(
            'normal' => ' %indicator% %message%',
            'normal_no_ansi' => ' %message%',

            'verbose' => ' %indicator% %message% (%elapsed:6s%)',
            'verbose_no_ansi' => ' %message% (%elapsed:6s%)',

            'very_verbose' => ' %indicator% %message% (%elapsed:6s%, %memory:6s%)',
            'very_verbose_no_ansi' => ' %message% (%elapsed:6s%, %memory:6s%)',
        );
    }
}
