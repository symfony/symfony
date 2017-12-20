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

use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Manages a local HTTP web server.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class WebServer
{
    const STARTED = 0;
    const STOPPED = 1;

    public function run(WebServerConfig $config, $disableOutput = true, callable $callback = null)
    {
        if ($this->isRunning()) {
            throw new \RuntimeException(sprintf('A process is already listening on http://%s.', $config->getAddress()));
        }

        $process = $this->createServerProcess($config);
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

    public function start(WebServerConfig $config, $pidFile = null)
    {
        $pidFile = $pidFile ?: $this->getDefaultPidFile();
        if ($this->isRunning($pidFile)) {
            throw new \RuntimeException(sprintf('A process is already listening on http://%s.', $config->getAddress()));
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

        $process = $this->createServerProcess($config);
        $process->disableOutput();
        $process->start();

        if (!$process->isRunning()) {
            throw new \RuntimeException('Unable to start the server process.');
        }

        file_put_contents($pidFile, $config->getAddress());

        // stop the web server when the lock file is removed
        while ($process->isRunning()) {
            if (!file_exists($pidFile)) {
                $process->stop();
            }

            sleep(1);
        }

        return self::STOPPED;
    }

    public function stop($pidFile = null)
    {
        $pidFile = $pidFile ?: $this->getDefaultPidFile();
        if (!file_exists($pidFile)) {
            throw new \RuntimeException('No web server is listening.');
        }

        unlink($pidFile);
    }

    public function getAddress($pidFile = null)
    {
        $pidFile = $pidFile ?: $this->getDefaultPidFile();
        if (!file_exists($pidFile)) {
            return false;
        }

        return file_get_contents($pidFile);
    }

    public function isRunning($pidFile = null)
    {
        $pidFile = $pidFile ?: $this->getDefaultPidFile();
        if (!file_exists($pidFile)) {
            return false;
        }

        $address = file_get_contents($pidFile);
        $pos = strrpos($address, ':');
        $hostname = substr($address, 0, $pos);
        $port = substr($address, $pos + 1);
        if (false !== $fp = @fsockopen($hostname, $port, $errno, $errstr, 1)) {
            fclose($fp);

            return true;
        }

        unlink($pidFile);

        return false;
    }

    /**
     * @return Process The process
     */
    private function createServerProcess(WebServerConfig $config)
    {
        $executable = $config->getExecutable();

        // we need to separate the executable from the rest of the string as StringInput won't handle it
        $firstPartOfCommand = explode(' ', $executable)[0];
        // remove the executable and keep remainder of parameters
        $remainder = substr($executable, strlen($firstPartOfCommand) + 1); //the one is a space

        $input = new StringInput($remainder);
        // newly added method to retrieve input parts
        $executableArray = $input->getCommandLineArray();
        $processArray = array_merge(array($firstPartOfCommand), $executableArray, array('-S', $config->getAddress(), $config->getRouter()));
        $process = new Process($processArray);
        $process->setWorkingDirectory($config->getDocumentRoot());
        $process->setTimeout(null);

        if (in_array('APP_ENV', explode(',', getenv('SYMFONY_DOTENV_VARS')))) {
            $process->setEnv(array('APP_ENV' => false));
            $process->inheritEnvironmentVariables();
        }

        return $process;
    }

    private function getDefaultPidFile()
    {
        return getcwd().'/.web-server-pid';
    }
}
