<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Command
{
    /**
     * @var Command|null
     */
    private $parent;

    /**
     * @var array
     */
    private $bits;

    /**
     * @var array
     */
    private $labels;

    /**
     * Constructor.
     *
     * @param Command $parent Parent command
     */
    public function __construct(Command $parent = null)
    {
        $this->parent = null;
        $this->bits   = array();
        $this->labels = array();
    }

    /**
     * Returns command as string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->join();
    }

    /**
     * Creates a new Command instance.
     *
     * @param Command $parent Parent command
     *
     * @return \Symfony\Component\Finder\Command New Command instance
     */
    static public function create(Command $parent = null)
    {
        return new self($parent);
    }

    /**
     * Appends a string or a Command instance.
     *
     * @param string|Command $bit
     *
     * @return \Symfony\Component\Finder\Command The current Command instance
     */
    public function add($bit)
    {
        $this->bits[] = $bit;

        return $this;
    }

    /**
     * Appends an argument, will be quoted.
     *
     * @param string $arg
     *
     * @return \Symfony\Component\Finder\Command The current Command instance
     */
    public function arg($arg)
    {
        $this->bits[] = escapeshellarg($arg);

        return $this;
    }

    /**
     * Appends escaped special command chars.
     *
     * @param string $esc
     *
     * @return \Symfony\Component\Finder\Command The current Command instance
     */
    public function cmd($esc)
    {
        $this->bits[] = escapeshellcmd($esc);

        return $this;
    }

    /**
     * Inserts a labeled command to feed later.
     *
     * @param string $label The unique label
     *
     * @return \Symfony\Component\Finder\Command The current Command instance
     *
     * @throws \RuntimeException If label already exists
     */
    public function ins($label)
    {
        if (isset($this->labels[$label])) {
            throw new \RuntimeException('Label "'.$label.'" already exists.');
        }

        $this->bits[] = self::create($this);
        $this->labels[$label] = count($this->bits)-1;

        return $this;
    }

    /**
     * Retrieves a previously labeled command.
     *
     * @param string $label
     *
     * @return \Symfony\Component\Finder\Command The labeled command
     */
    public function get($label)
    {
        return $this->labels[$label];
    }

    /**
     * Returns parent command (if any).
     *
     * @return Command Parent command
     *
     * @throws \RuntimeException If command has no parent
     */
    public function end()
    {
        if (null === $this->parent) {
            throw new \RuntimeException('Calling end on root command dont makes sense.');
        }

        return $this->parent;
    }

    /**
     * Executes current command.
     *
     * @return array The command result
     */
    public function execute()
    {
        exec($this->join(), $output, $code);

        if (0 !== $code) {
            throw new \RuntimeException('Execution failed with return code: '.$code.'.');
        }

        return $output ?: array();
    }

    /**
     * Joins bits.
     *
     * @return string
     */
    private function join()
    {
        return implode(' ', array_filter(
            array_map(function($bit) { return $bit instanceof Command ? $bit->join : ($bit ?: null); }, $this->bits),
            function($bit) { return null !== $bit; }
        ));
    }
}
