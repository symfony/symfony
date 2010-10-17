<?php

namespace Symfony\Component\Process;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PhpProcess runs a PHP script in an independent process.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PhpProcess extends Process
{
    /**
     * Constructor.
     *
     * @param string  $script  The PHP script to run (as a string)
     * @param string  $cwd     The working directory
     * @param array   $env     The environment variables
     * @param integer $timeout The timeout in seconds
     * @param array   $options An array of options for proc_open
     */
    public function __construct($script, $cwd = null, array $env = array(), $timeout = 60, array $options = array())
    {
        parent::__construct(null, $cwd, $env, $script, $timeout, $options);
    }

    /**
     * Sets the path to the PHP binary to use.
     */
    public function setPhpBinary($php)
    {
        $this->commandline = $php;
    }

    /**
     * run the process.
     *
     * @param Closure|string|array $callback A PHP callback to run whenever there is some
     *                                       output available on STDOUT or STDERR
     *
     * @return integer The exit status code
     */
    public function run($callback = null)
    {
        if (null === $this->commandline) {
            $this->commandline = $this->getPhpBinary();
        }

        parent::run($callback);
    }

    /**
     * Returns the PHP binary path.
     *
     * @return string The PHP binary path
     *
     * @throws \RuntimeException When defined PHP_PATH is not executable or not found
     */
    static public function getPhpBinary()
    {
        if (getenv('PHP_PATH')) {
            if (!is_executable($php = getenv('PHP_PATH'))) {
                throw new \RuntimeException('The defined PHP_PATH environment variable is not a valid PHP executable.');
            }

            return $php;
        }

        $suffixes = DIRECTORY_SEPARATOR == '\\' ? (getenv('PATHEXT') ? explode(PATH_SEPARATOR, getenv('PATHEXT')) : array('.exe', '.bat', '.cmd', '.com')) : array('');
        foreach ($suffixes as $suffix) {
            if (is_executable($php = PHP_BINDIR.DIRECTORY_SEPARATOR.'php'.$suffix)) {
                return $php;
            }
        }

        throw new \RuntimeException('Unable to find the PHP executable.');
    }
}
