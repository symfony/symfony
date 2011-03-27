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
 * PhpProcess runs a PHP script in an independent process.
 *
 * $p = new PhpProcess('<?php echo "foo"; ?>');
 * $p->run();
 * print $p->getOutput()."\n";
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
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
     *
     * @api
     */
    public function __construct($script, $cwd = null, array $env = array(), $timeout = 60, array $options = array())
    {
        parent::__construct(null, $cwd, $env, $script, $timeout, $options);
    }

    /**
     * Sets the path to the PHP binary to use.
     *
     * @api
     */
    public function setPhpBinary($php)
    {
        $this->setCommandLine($php);
    }

    /**
     * Runs the process.
     *
     * @param Closure|string|array $callback A PHP callback to run whenever there is some
     *                                       output available on STDOUT or STDERR
     *
     * @return integer The exit status code
     *
     * @api
     */
    public function run($callback = null)
    {
        if (null === $this->getCommandLine()) {
            $this->setCommandLine($this->getPhpBinary());
        }

        return parent::run($callback);
    }

    /**
     * Returns the PHP binary path.
     *
     * @return string The PHP binary path
     *
     * @throws \RuntimeException When defined PHP_PATH is not executable or not found
     */
    private function getPhpBinary()
    {
        if ($php = getenv('PHP_PATH')) {
            if (!is_executable($php)) {
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

        if ($php = getenv('PHP_PEAR_PHP_BIN')) {
            if (is_executable($php)) {
                return $php;
            }
        }

        throw new \RuntimeException('Unable to find the PHP executable.');
    }
}
