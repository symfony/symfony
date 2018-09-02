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
 *     $p = new PhpProcess('<?php echo "foo"; ?>');
 *     $p->run();
 *     print $p->getOutput()."\n";
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class PhpProcess extends Process
{
    /**
     * @param string      $script  The PHP script to run (as a string)
     * @param string|null $cwd     The working directory or null to use the working dir of the current PHP process
     * @param array|null  $env     The environment variables or null to use the same environment as the current PHP process
     * @param int         $timeout The timeout in seconds
     */
    public function __construct(string $script, string $cwd = null, array $env = null, int $timeout = 60)
    {
        $executableFinder = new PhpExecutableFinder();
        if (false === $php = $executableFinder->find(false)) {
            $php = null;
        } else {
            $php = array_merge(array($php), $executableFinder->findArguments());
        }
        if ('phpdbg' === \PHP_SAPI) {
            $file = tempnam(sys_get_temp_dir(), 'dbg');
            file_put_contents($file, $script);
            register_shutdown_function('unlink', $file);
            $php[] = $file;
            $script = null;
        }

        parent::__construct($php, $cwd, $env, $script, $timeout);
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
    public function start(callable $callback = null, array $env = array())
    {
        if (null === $this->getCommandLine()) {
            throw new RuntimeException('Unable to find the PHP executable.');
        }

        parent::start($callback, $env);
    }
}
