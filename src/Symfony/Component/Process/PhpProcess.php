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

use Symfony\Component\Process\Exception\RuntimeException;

/**
 * PhpProcess runs a PHP script in an independent process.
 *
 * $p = new PhpProcess('<?php echo "foo"; ?>');
 * $p->run();
 * print $p->getOutput()."\n";
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class PhpProcess extends Process
{
    /**
     * Constructor.
     *
     * @param string      $script  The PHP script to run (as a string)
     * @param string|null $cwd     The working directory or null to use the working dir of the current PHP process
     * @param array|null  $env     The environment variables or null to use the same environment as the current PHP process
     * @param int         $timeout The timeout in seconds
     * @param array       $options An array of options for proc_open
     */
    public function __construct($script, $cwd = null, array $env = null, $timeout = 60, array $options = array())
    {
        $executableFinder = new PhpExecutableFinder();
        if (false === $php = $executableFinder->find()) {
            $php = null;
        }
        if ('phpdbg' === PHP_SAPI) {
            $file = tempnam(sys_get_temp_dir(), 'dbg');
            file_put_contents($file, $script);
            register_shutdown_function('unlink', $file);
            $php .= ' '.ProcessUtils::escapeArgument($file);
            $script = null;
        }
        if ('\\' !== DIRECTORY_SEPARATOR && null !== $php) {
            // exec is mandatory to deal with sending a signal to the process
            // see https://github.com/symfony/symfony/issues/5030 about prepending
            // command with exec
            $php = 'exec '.$php;
        }

        parent::__construct($php, $cwd, $env, $script, $timeout, $options);
    }

    /**
     * Sets the path to the PHP binary to use.
     */
    public function setPhpBinary($php)
    {
        $this->setCommandLine($php);
    }

    /**
     * {@inheritdoc}
     */
    public function start($callback = null)
    {
        if (null === $this->getCommandLine()) {
            throw new RuntimeException('Unable to find the PHP executable.');
        }

        parent::start($callback);
    }
}
