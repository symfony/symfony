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

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class WebServerConfig
{
    private $hostname;
    private $port;
    private $documentRoot;
    private $env;
    private $router;

    public function __construct($documentRoot, $env, $address = null, $router = null)
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
        $this->router = $router ?: __DIR__.'/Resources/router.php';

        if (null === $address) {
            $this->hostname = '127.0.0.1';
            $this->port = $this->findBestPort();
        } elseif (false !== $pos = strrpos($address, ':')) {
            $this->hostname = substr($address, 0, $pos);
            $this->port = substr($address, $pos + 1);
        } elseif (ctype_digit($address)) {
            $this->hostname = '127.0.0.1';
            $this->port = $address;
        } else {
            $this->hostname = $address;
            $this->port = $this->findBestPort();
        }

        if (!ctype_digit($this->port)) {
            throw new \InvalidArgumentException(sprintf('Port "%s" is not valid.', $this->port));
        }
    }

    public function getDocumentRoot()
    {
        return $this->documentRoot;
    }

    public function getEnv()
    {
        return $this->env;
    }

    public function getRouter()
    {
        return $this->router;
    }

    public function getHostname()
    {
        return $this->hostname;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getAddress()
    {
        return $this->hostname.':'.$this->port;
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

    private function findBestPort()
    {
        $port = 8000;
        while (false !== $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1)) {
            fclose($fp);
            if ($port++ >= 8100) {
                throw new \RuntimeException('Unable to find a port available to run the web server.');
            }
        }

        return $port;
    }
}
