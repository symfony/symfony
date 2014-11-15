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

use Symfony\Component\Process\Exception\InvalidArgumentException;

/**
 * @author Romain Neutron <imprec@gmail.com>
 *
 * @api
 */
class CommandLine
{
    const DEFAULT_PLACEHOLDER = '{}';
    private $commandLine;
    private $placeholder;
    private $disabled = false;

    public function __construct($commandLine, $placeholder = self::DEFAULT_PLACEHOLDER)
    {
        $this->commandLine = (string) $commandLine;
        $this->setPlaceholder($placeholder);
    }

    /**
     * @return string
     *
     * @api
     */
    public function getCommandLine()
    {
        return $this->commandLine;
    }

    /**
     * @param string $commandLine
     *
     * @return CommandLine
     *
     * @api
     */
    public function setCommandLine($commandLine)
    {
        $this->commandLine = $commandLine;

        return $this;
    }

    /**
     * @return string
     *
     * @api
     */
    public function getPlaceholder()
    {
        return $this->placeholder;
    }

    /**
     * @param string $placeholder
     *
     * @return CommandLine
     *
     * @throws InvalidArgumentException
     *
     * @api
     */
    public function setPlaceholder($placeholder)
    {
        if (null !== $placeholder && 0 === strlen($placeholder)) {
            throw new InvalidArgumentException('Invalid placeholder');
        }

        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * @param array $parameters
     *
     * @return string
     *
     * @throws InvalidArgumentException
     *
     * @api
     */
    public function prepare(array $parameters)
    {
        if ($this->disabled) {
            return $this->commandLine;
        }

        $placeholders = $this->countPlaceholders(array_filter(array_keys($parameters), function ($value) { return !is_numeric($value); }));

        if (count($parameters) !== $placeholders) {
            throw new InvalidArgumentException('Invalid number of bound parameters');
        }

        if (0 === $placeholders) {
            return $this->commandLine;
        }

        $command = '';
        $offset = 0;

        foreach ($parameters as $key => $value) {
            $placeholder = is_numeric($key) ? $this->placeholder : $key;

            $pos = strpos($this->commandLine, $placeholder, $offset);
            $command .= substr($this->commandLine, $offset, $pos - $offset);
            $offset = $pos + strlen($placeholder);
            $command .= $this->escape($value);
        }
        $command .= substr($this->commandLine, $offset);

        return $command;
    }

    /**
     * @internal
     */
    public function disableArguments()
    {
        $this->disabled = true;
    }

    /**
     * @param array $placeholders
     *
     * @return int
     */
    private function countPlaceholders(array $placeholders)
    {
        if (null === $this->placeholder && 0 === count($placeholders)) {
            return 0;
        }

        $total = preg_match_all('#' . preg_quote($this->placeholder, '#') . '#', $this->commandLine, $matches);

        foreach ($placeholders as $placeholder) {
            $total += preg_match_all('#' . preg_quote($placeholder, '#') . '#', $this->commandLine, $matches);
        }

        return $total;
    }

    /**
     * @param string $string
     *
     * @return string
     */
    private function escape($string)
    {
        return ProcessUtils::escapeArgument($string);
    }
}
