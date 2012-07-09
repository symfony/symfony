<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Bundle\Tests\Fixtures\ExtensionAbsentBundle;

use Symfony\Component\Bundle\Bundle;
use Symfony\Component\Bundle\CommandBundleInterface;
use Symfony\Component\Bundle\CommandBundleService;
use Symfony\Component\Console\Application;

class ExtensionAbsentBundle extends Bundle implements CommandBundleInterface
{
    public function registerCommands(Application $application)
    {
        $commandBundleRegistrationService = new CommandBundleService;

        return $commandBundleRegistrationService->registerCommands($this, $application);
    }
}
