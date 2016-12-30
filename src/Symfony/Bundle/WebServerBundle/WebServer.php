<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebServerBundle;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * Manages a local HTTP web server.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class WebServer
{
    const STARTED = 0;
    const STOPPED = 1;

    private $hostname;
    private $port;
    private $documentRoot;
    private $env;

    public function __construct($address = '127.0.0.1:8000')
    {
        if (false !== $pos = strrpos($address, ':')) {
            $this->hostname = substr($address, 0, $pos);
            $this->port = substr($address, $pos + 1);
        } elseif (ctype_digit($address)) {
            $this->hostname = '127.0.0.1';
            $this->port = $address;
        } else {
            $this->hostname = $address;
            $this->port = 80;
        }
    }

    public function getAddress()
    {
        return $this->hostname.':'.$this->port;
    }

    public function setConfig($documentRoot, $env)
    {
        if (!is_dir($documentRoot)) {
            throw new \InvalidArgumentException(sprintf('The document root directory "%s" does not exist.', $documentRoot));
        }

        if (null === $file = $this->guessFrontController($documentRoot, $env)) {
            throw new \InvalidArgumentException(sprintf('Unable to guess the front controller under "%s".', $documentRoot));
        }

        putenv('APP_FRONT_CONTROLLER='.$file);

        $this->documentRoot = $documentRoot;
        $this->env = $env;
    }

    public function run($router = null, $disableOutput = true, callable $callback = null)
    {
        if ($this->isRunning()) {
            throw new \RuntimeException(sprintf('A process is already listening on http://%s.', $this->getAddress()));
        }

        $process = $this->createServerProcess($router);
        if ($disableOutput) {
            $process->disableOutput();
            $callback = null;
        } else {
            try {
                $process->setTty(true);
                $callback = null;
            } catch (RuntimeException $e) {
            }
        }

        $process->run($callback);

        if (!$process->isSuccessful()) {
            $error = 'Server terminated unexpectedly.';
            if ($process->isOutputDisabled()) {
                $error .= ' Run the command again with -v option for more details.';
            }

            throw new \RuntimeException($error);
        }
    }

    public function start($router = null)
    {
        if ($this->isRunning()) {
            throw new \RuntimeException(sprintf('A process is already listening on http://%s.', $this->getAddress()));
        }

        $pid = pcntl_fork();

        if ($pid < 0) {
            throw new \RuntimeException('Unable to start the server process.');
        }

        if ($pid > 0) {
            return self::STARTED;
        }

        if (posix_setsid() < 0) {
            throw new \RuntimeException('Unable to set the child process as session leader.');
        }

        $process = $this->createServerProcess($router);
        $process->disableOutput();
        $process->start();

        if (!$process->isRunning()) {
            throw new \RuntimeException('Unable to start the server process.');
        }

        $lockFile = $this->getLockFile();
        touch($lockFile);

        // stop the web server when the lock file is removed
        while ($process->isRunning()) {
            if (!file_exists($lockFile)) {
                $process->stop();
            }

            sleep(1);
        }

        return self::STOPPED;
    }

    public function stop()
    {
        if (!file_exists($lockFile = $this->getLockFile())) {
            throw new \RuntimeException(sprintf('No web server is listening on http://%s.', $this->getAddress()));
        }

        unlink($lockFile);
    }

    public function isRunning()
    {
        if (false !== $fp = @fsockopen($this->hostname, $this->port, $errno, $errstr, 1)) {
            fclose($fp);

            return true;
        }

        if (file_exists($lockFile = $this->getLockFile())) {
            unlink($lockFile);
        }

        return false;
    }

    /**
     * @return Process The process
     */
    private function createServerProcess($router = null)
    {
        $finder = new PhpExecutableFinder();
        if (false === $binary = $finder->find()) {
            throw new \RuntimeException('Unable to find the PHP binary.');
        }

        $builder = new ProcessBuilder(array($binary, '-S', $this->getAddress(), $router));
        $builder->setWorkingDirectory($this->documentRoot);
        $builder->setTimeout(null);

        return $builder->getProcess();
    }

    /**
     * Determines the name of the lock file for a particular PHP web server process.
     *
     * @return string The filename
     */
    private function getLockFile()
    {
        return sys_get_temp_dir().'/'.strtr($this->getAddress(), '.:', '--').'.pid';
    }

    private function guessFrontController($documentRoot, $env)
    {
        foreach (array('app', 'index') as $prefix) {
            $file = sprintf('%s_%s.php', $prefix, $env);
            if (file_exists($documentRoot.'/'.$file)) {
                return $file;
            }

            $file = sprintf('%s.php', $prefix);
            if (file_exists($documentRoot.'/'.$file)) {
                return $file;
            }
        }
    }
}
