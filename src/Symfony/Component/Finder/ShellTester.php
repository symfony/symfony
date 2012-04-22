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
class ShellTester
{
    const TYPE_UNIX    = 1;
    const TYPE_MAC     = 2;
    const TYPE_CYGWIN  = 3;
    const TYPE_WINDOWS = 4;

    /**
     * @var string|null
     */
    private $type;

    /**
     * Returns guessed OS type.
     *
     * @return int
     */
    public function getType()
    {
        if (null === $this->type) {
            $this->type = $this->guessType();
        }

        return $this->type;
    }

    /**
     * Tests if a command is available.
     *
     * @param string $command
     *
     * @return bool
     */
    public function testCommand($command)
    {
        exec($command, $output, $code);

        return 0 === $code;
    }

    /**
     * Guesses OS type.
     *
     * @return int
     */
    private function guessType()
    {
        $os = strtolower(PHP_OS);

        if (false !== strpos($os, 'cygwin')) {
            return self::TYPE_CYGWIN;
        }

        if (false !== strpos($os, 'darwin')) {
            return self::TYPE_MAC;
        }

        if (0 === strpos($os, 'win')) {
            return self::TYPE_WINDOWS;
        }

        return self::TYPE_UNIX;
    }
}
