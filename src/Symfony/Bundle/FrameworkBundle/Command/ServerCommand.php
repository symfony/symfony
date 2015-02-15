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
<<<<<<< HEAD
        if (version_compare(phpversion(), '5.4.0', '<') || defined('HHVM_VERSION')) {
=======
        if (defined('HHVM_VERSION')) {
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
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
}
