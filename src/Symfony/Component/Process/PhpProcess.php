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
    private $executableFinder;

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

        $this->executableFinder = new PhpExecutableFinder();
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
     * Starts the process and returns after sending the STDIN.
     *
     * This method blocks until all STDIN data is sent to the process then it
     * returns while the process runs in the background.
     *
     * The termination of the process can be awaited with wait().
     *
     * The callback receives the type of output (out or err) and some bytes from
     * the output in real-time while writing the standard input to the process.
     * It allows to have feedback from the independent process during execution.
     * If there is no callback passed, the wait() method can be called
     * with true as a second parameter then the callback will get all data occurred
     * in (and since) the start call.
     *
     * @param Closure|string|array $callback A PHP callback to run whenever there is some
     *                                       output available on STDOUT or STDERR
     *
     * @throws \RuntimeException When process can't be launch or is stopped
     * @throws \RuntimeException When process is already running
     */
    public function start($callback = null)
    {
        if (null === $this->getCommandLine()) {
            if (false === $php = $this->executableFinder->find()) {
                throw new \RuntimeException('Unable to find the PHP executable.');
            }
            $this->setCommandLine($php);
        }

        return parent::start($callback);
    }
}
