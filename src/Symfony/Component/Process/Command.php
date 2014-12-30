<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process;

/**
 * @author Romain Neutron <imprec@gmail.com>
 */
class Command
{
    private $parts = array();
    private $appended = array();
    private $piped;
    private $redirects = array();

    public function __construct($parts = null, $escape = true)
    {
        if (null !== $parts) {
            $this->add($parts, $escape);
        }
    }

    public function add($parts, $escape = true, $prepend = false)
    {
        if (!is_array($parts)) {
            $parts = array($parts);
        }

        if ($prepend) {
            $this->parts = array_merge(($escape ? array_map(array($this, 'escape'), $parts) : $parts), $this->parts);
        } else {
            $this->parts = array_merge($this->parts, $escape ? array_map(array($this, 'escape'), $parts) : $parts);
        }

        return $this;
    }

    public function append(Command $command)
    {
        $this->appended[] = $command;

        return $this;
    }

    public function pipe(Command $command)
    {
        $this->piped = $command;

        return $command;
    }

    public function redirect($fd, $target = null, $append = false)
    {
        $this->redirects[$fd] = array('target' => $target, 'append' => $append);

        return $this;
    }

    public function prepareForexecution()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            return 'cmd /V:ON /E:ON /C "('.$this.')"';
        }

        return (string) $this;
    }

    public function __toString()
    {
        $command = implode(' ', $this->parts).(count($this->appended) > 0 ? '; ' : ' ').implode('; ', $this->appended);

        if ($this->hasRedirects()) {
            $command = '('.$command.$this->getRedirects().')';
        }

        $command .= ($this->piped ? '| '.$this->piped : '');

        return trim($command);
    }

    public static function fromString($commandline)
    {
        $command = new self();

        $command->add($commandline, false);

        return $command;
    }

    private function hasRedirects()
    {
        return 0 < count($this->redirects);
    }

    private function getRedirects()
    {
        $redirects = '';

        foreach ($this->redirects as $fd => $props) {
            if (null === $props['target']) {
                $props['target'] = defined('PHP_WINDOWS_VERSION_BUILD') ? 'NUL' : '/dev/null';
            }
            $redirects .= ' '.$fd.'>'.($props['append'] ? '>' : '').self::escape($props['target']);
        }

        return $redirects ? $redirects : '';
    }

    /**
     * Escapes a string to be used as a shell argument.
     *
     * @param string $argument The argument that will be escaped
     *
     * @return string The escaped argument
     *
     * @internal Method is a static public to provide BC to ProcessUtils until Symfony 3.0
     *           This method will be a private non-static as of Symfony 3.0
     */
    public static function escape($argument)
    {
        //Fix for PHP bug #43784 escapeshellarg removes % from given string
        //Fix for PHP bug #49446 escapeshellarg doesn't work on Windows
        //@see https://bugs.php.net/bug.php?id=43784
        //@see https://bugs.php.net/bug.php?id=49446
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            if ('' === $argument) {
                return escapeshellarg($argument);
            }

            $escapedArgument = '';
            $quote =  false;
            foreach (preg_split('/(")/i', $argument, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $part) {
                if ('"' === $part) {
                    $escapedArgument .= '\\"';
                } elseif (self::isSurroundedBy($part, '%')) {
                    // Avoid environment variable expansion
                    $escapedArgument .= '^%"'.substr($part, 1, -1).'"^%';
                } else {
                    // escape trailing backslash
                    if ('\\' === substr($part, -1)) {
                        $part .= '\\';
                    }
                    $quote = true;
                    $escapedArgument .= $part;
                }
            }
            if ($quote) {
                $escapedArgument = '"'.$escapedArgument.'"';
            }

            return $escapedArgument;
        }

        return escapeshellarg($argument);
    }

    private static function isSurroundedBy($arg, $char)
    {
        return 2 < strlen($arg) && $char === $arg[0] && $char === $arg[strlen($arg) - 1];
    }
}
