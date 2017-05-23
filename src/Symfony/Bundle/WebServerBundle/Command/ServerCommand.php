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

use Symfony\Component\Console\Command\Command;

/**
 * Base methods for commands related to a local web server.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * @internal
 */
abstract class ServerCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return !defined('HHVM_VERSION') && parent::isEnabled();
    }
}
