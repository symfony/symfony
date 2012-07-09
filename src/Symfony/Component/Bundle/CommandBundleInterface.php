<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Bundle;

use Symfony\Component\Console\Application;

/**
 * CommandBundleInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
interface CommandBundleInterface
{
    /**
     * Registers Commands.
     *
     * @param Application $application An Application instance
     */
    public function registerCommands(Application $application);
}
