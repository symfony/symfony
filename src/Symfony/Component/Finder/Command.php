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
     * @var array
     */
    private $bits;

    /**
     * @param string $command
     */
    public function __construct($bit = null)
    {
        if ($bit) {
            $this->bits[] = $bit;
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return implode(' ', $this->bits);
    }

    /**
     * @param string $command
     *
     * @return \Symfony\Component\Finder\Command
     */
    static public function create($command)
    {
        return new self($command);
    }

    /**
     * @param string $bit
     *
     * @return \Symfony\Component\Finder\Command The current Command instance
     */
    public function add($bit)
    {
        $this->bits[] = (string) $bit;

        return $this;
    }

    /**
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
     * @param $output
     *
     * @return int
     */
    public function execute(&$output)
    {
        exec(implode(' ', $this->bits), $output, $code);

        return $code;
    }
}
