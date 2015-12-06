<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

/**
 * Base methods for commands related to PHP's built-in web server.
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
        if (PHP_VERSION_ID < 50400 || defined('HHVM_VERSION')) {
            return false;
        }

        if (!class_exists('Symfony\Component\Process\Process')) {
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
}
