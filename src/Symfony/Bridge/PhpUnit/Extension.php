<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use PHPUnit\Metadata\Parser\Registry as MetadataRegistry;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

class Extension implements \PHPUnit\Runner\Extension\Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(
            new class() implements PreparedSubscriber {
                public function notify(Prepared $event): void
                {
                    $test = $event->test();
                    if (!$test->isTestMethod()) {
                        return;
                    }
                    /** @var TestMethod $test */

                    $shouldErrorHandlerBeUsed = !MetadataRegistry::parser()
                        ->forMethod($test->className(), $test->methodName())
                        ->isWithoutErrorHandler()
                        ->isNotEmpty();

                    if ($shouldErrorHandlerBeUsed) {
                        DeprecationErrorHandler::enablePhpUnitErrorHandler();
                    }
                }
            }
        );

        $facade->registerSubscriber(
            new class() implements FinishedSubscriber {
                public function notify(Finished $event): void
                {
                    DeprecationErrorHandler::disablePhpUnitErrorHandler();
                }
            }
        );

        DeprecationErrorHandler::disablePhpUnitErrorHandler();
    }
}
