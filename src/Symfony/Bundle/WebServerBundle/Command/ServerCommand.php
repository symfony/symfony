<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebServerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Base methods for commands related to a local web server.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
abstract class ServerCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        if (defined('HHVM_VERSION')) {
            return false;
        }

        return parent::isEnabled();
    }

    /**
     * Determines the name of the lock file for a particular PHP web server process.
     *
     * @param string $address An address/port tuple
     *
     * @return string The filename
     */
    protected function getLockFile($address)
    {
        return sys_get_temp_dir().'/'.strtr($address, '.:', '--').'.pid';
    }

    protected function isOtherServerProcessRunning($address)
    {
        $lockFile = $this->getLockFile($address);
        if (file_exists($lockFile)) {
            return true;
        }

        list($hostname, $port) = explode(':', $address);

        $fp = @fsockopen($hostname, $port, $errno, $errstr, 5);

        if (false !== $fp) {
            fclose($fp);

            return true;
        }

        return false;
    }

    /**
     * Determine the absolute file path for the router script, using the environment to choose a standard script
     * if no custom router script is specified.
     *
     * @param string       $documentRoot Document root
     * @param string|null  $router       File path of the custom router script, if set by the user; otherwise null
     * @param string       $env          The application environment
     *
     * @return string|bool The absolute file path of the router script, or false on failure
     */
    protected function determineRouterScript($documentRoot, $router, $env)
    {
        if (null !== $router) {
            return realpath($router);
        }

        if (false === $frontController = $this->guessFrontController($documentRoot, $env)) {
            return false;
        }

        putenv('APP_FRONT_CONTROLLER='.$frontController);

        return realpath($this
            ->getContainer()
            ->get('kernel')
            ->locateResource(sprintf('@WebServerBundle/Resources/router.php'))
        );
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

        return false;
    }
}
