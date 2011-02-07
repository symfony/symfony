<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Output;

/**
 * Base class for output classes.
 *
 * There is three level of verbosity:
 *
 *  * normal: no option passed (normal output - information)
 *  * verbose: -v (more output - debug)
 *  * quiet: -q (no output)
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Output implements OutputInterface
{
    const VERBOSITY_QUIET   = 0;
    const VERBOSITY_NORMAL  = 1;
    const VERBOSITY_VERBOSE = 2;

    const OUTPUT_NORMAL = 0;
    const OUTPUT_RAW = 1;
    const OUTPUT_PLAIN = 2;

    protected $verbosity;
    protected $decorated;

    static protected $styles = array(
        'error'    => array('bg' => 'red', 'fg' => 'white'),
        'info'     => array('fg' => 'green'),
        'comment'  => array('fg' => 'yellow'),
        'question' => array('bg' => 'cyan', 'fg' => 'black'),
    );
    static protected $options    = array('bold' => 1, 'underscore' => 4, 'blink' => 5, 'reverse' => 7, 'conceal' => 8);
    static protected $foreground = array('black' => 30, 'red' => 31, 'green' => 32, 'yellow' => 33, 'blue' => 34, 'magenta' => 35, 'cyan' => 36, 'white' => 37);
    static protected $background = array('black' => 40, 'red' => 41, 'green' => 42, 'yellow' => 43, 'blue' => 44, 'magenta' => 45, 'cyan' => 46, 'white' => 47);

    /**
     * Constructor.
     *
     * @param integer $verbosity The verbosity level (self::VERBOSITY_QUIET, self::VERBOSITY_NORMAL, self::VERBOSITY_VERBOSE)
     * @param Boolean $decorated Whether to decorate messages or not (null for auto-guessing)
     */
    public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = null)
    {
        $this->decorated = (Boolean) $decorated;
        $this->verbosity = null === $verbosity ? self::VERBOSITY_NORMAL : $verbosity;
    }

    /**
     * Sets a new style.
     *
     * @param string $name    The style name
     * @param array  $options An array of options
     */
    static public function setStyle($name, $options = array())
    {
        static::$styles[strtolower($name)] = $options;
    }

    /**
     * Sets the decorated flag.
     *
     * @param Boolean $decorated Whether to decorated the messages or not
     */
    public function setDecorated($decorated)
    {
        $this->decorated = (Boolean) $decorated;
    }

    /**
     * Gets the decorated flag.
     *
     * @return Boolean true if the output will decorate messages, false otherwise
     */
    public function isDecorated()
    {
        return $this->decorated;
    }

    /**
     * Sets the verbosity of the output.
     *
     * @param integer $level The level of verbosity
     */
    public function setVerbosity($level)
    {
        $this->verbosity = (int) $level;
    }

    /**
     * Gets the current verbosity of the output.
     *
     * @return integer The current level of verbosity
     */
    public function getVerbosity()
    {
        return $this->verbosity;
    }

    /**
     * Writes a message to the output and adds a newline at the end.
     *
     * @param string|array $messages The message as an array of lines of a single string
     * @param integer      $type     The type of output
     */
    public function writeln($messages, $type = 0)
    {
        $this->write($messages, true, $type);
    }

    /**
     * Writes a message to the output.
     *
     * @param string|array $messages The message as an array of lines of a single string
     * @param Boolean      $newline  Whether to add a newline or not
     * @param integer      $type     The type of output
     *
     * @throws \InvalidArgumentException When unknown output type is given
     */
    public function write($messages, $newline = false, $type = 0)
    {
        if (self::VERBOSITY_QUIET === $this->verbosity) {
            return;
        }

        if (!is_array($messages)) {
            $messages = array($messages);
        }

        foreach ($messages as $message) {
            switch ($type) {
                case Output::OUTPUT_NORMAL:
                    $message = $this->format($message);
                    break;
                case Output::OUTPUT_RAW:
                    break;
                case Output::OUTPUT_PLAIN:
                    $message = strip_tags($this->format($message));
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unknown output type given (%s)', $type));
            }

            $this->doWrite($message, $newline);
        }
    }

    /**
     * Writes a message to the output.
     *
     * @param string  $message A message to write to the output
     * @param Boolean $newline Whether to add a newline or not
     */
    abstract public function doWrite($message, $newline);

    /**
     * Formats a message according to the given styles.
     *
     * @param  string $message The message to style
     *
     * @return string The styled message
     */
    protected function format($message)
    {
        $message = preg_replace_callback('#<([a-z][a-z0-9\-_=;]+)>#i', array($this, 'replaceStartStyle'), $message);

        return preg_replace_callback('#</([a-z][a-z0-9\-_]*)?>#i', array($this, 'replaceEndStyle'), $message);
    }

    /**
     * Replaces the starting style of the output.
     *
     * @param array $match
     *
     * @return string The replaced style
     *
     * @throws \InvalidArgumentException When style is unknown
     */
    protected function replaceStartStyle($match)
    {
        if (!$this->decorated) {
            return '';
        }

        if (isset(static::$styles[strtolower($match[1])])) {
            $parameters = static::$styles[strtolower($match[1])];
        } else {
            // bg=blue;fg=red
            if (!preg_match_all('/([^=]+)=([^;]+)(;|$)/', strtolower($match[1]), $matches, PREG_SET_ORDER)) {
                throw new \InvalidArgumentException(sprintf('Unknown style "%s".', $match[1]));
            }

            $parameters = array();
            foreach ($matches as $match) {
                $parameters[$match[1]] = $match[2];
            }
        }

        $codes = array();

        if (isset($parameters['fg'])) {
            $codes[] = static::$foreground[$parameters['fg']];
        }

        if (isset($parameters['bg'])) {
            $codes[] = static::$background[$parameters['bg']];
        }

        foreach (static::$options as $option => $value) {
            if (isset($parameters[$option]) && $parameters[$option]) {
                $codes[] = $value;
            }
        }

        return "\033[".implode(';', $codes).'m';
    }

    /**
     * Replaces the end style.
     *
     * @param string $match The text to match
     *
     * @return string The end style
     */
    protected function replaceEndStyle($match)
    {
        if (!$this->decorated) {
            return '';
        }

        return "\033[0m";
    }
}
