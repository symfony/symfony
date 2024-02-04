<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Event\TestRunner\Configured;
use PHPUnit\Event\TestRunner\ConfiguredSubscriber;
use PHPUnit\Runner\Extension\Facade as ExtensionFacade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Application;
use Symfony\Bridge\PhpUnit\Extension;

class CommandForV10
{
    public static function main(bool $exit = true): int
    {
        EventFacade::instance()->registerSubscriber(
            new class() implements ConfiguredSubscriber {
                public function notify(Configured $event): void
                {
                    $configuration = $event->configuration();
                    if ($configuration->noExtensions()) {
                        return;
                    }

                    foreach ($configuration->extensionBootstrappers() as $bootstrapper) {
                        if (Extension::class === $bootstrapper['className']) {
                            return;
                        }
                    }

                    $extension = new Extension();
                    $extension->bootstrap($configuration, new ExtensionFacade(), ParameterCollection::fromArray([]));
                }
            }
        );

        $shellExitCode = (new Application)->run($_SERVER['argv']);

        if ($exit) {
            exit($shellExitCode);
        }

        return $shellExitCode;
    }
}
