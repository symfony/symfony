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

use PHPUnit\TextUI\Command;
use PHPUnit\Util\Configuration;
use PHPUnit\Util\ErrorHandler;

/**
 * Since PHPUnit v8.3 ErrorHandler is invokable and requires variables in constructor.
 * This class reuses PHPUnit infrastructure to get these variables.
 *
 * @author Dmitrii Poddubnyi <dpoddubny@gmail.com>
 *
 * @internal
 */
class ErrorHandlerCallerV83 extends Command
{
    public function __construct(array $argv)
    {
        $this->handleArguments($argv);
    }

    public function handleError($type, $msg, $file, $line, $context)
    {
        $arguments = $this->arguments;
        $this->handleConfiguration($arguments);
        $object = new ErrorHandler(
            $arguments['convertDeprecationsToExceptions'],
            $arguments['convertErrorsToExceptions'],
            $arguments['convertNoticesToExceptions'],
            $arguments['convertWarningsToExceptions']
        );

        return $object($type, $msg, $file, $line, $context);
    }

    /**
     * This is simplified version of PHPUnit\TextUI\TestRunner::handleConfiguration.
     * https://github.com/sebastianbergmann/phpunit/blob/8.3.2/src/TextUI/TestRunner.php#L815-L1243.
     */
    private function handleConfiguration(array &$arguments): void
    {
        if (isset($arguments['configuration']) &&
            !$arguments['configuration'] instanceof Configuration) {
            $arguments['configuration'] = Configuration::getInstance(
                $arguments['configuration']
            );
        }

        if (isset($arguments['configuration'])) {
            $arguments['configuration']->handlePHPConfiguration();

            $phpunitConfiguration = $arguments['configuration']->getPHPUnitConfiguration();

            if (isset($phpunitConfiguration['convertDeprecationsToExceptions']) && !isset($arguments['convertDeprecationsToExceptions'])) {
                $arguments['convertDeprecationsToExceptions'] = $phpunitConfiguration['convertDeprecationsToExceptions'];
            }

            if (isset($phpunitConfiguration['convertErrorsToExceptions']) && !isset($arguments['convertErrorsToExceptions'])) {
                $arguments['convertErrorsToExceptions'] = $phpunitConfiguration['convertErrorsToExceptions'];
            }

            if (isset($phpunitConfiguration['convertNoticesToExceptions']) && !isset($arguments['convertNoticesToExceptions'])) {
                $arguments['convertNoticesToExceptions'] = $phpunitConfiguration['convertNoticesToExceptions'];
            }

            if (isset($phpunitConfiguration['convertWarningsToExceptions']) && !isset($arguments['convertWarningsToExceptions'])) {
                $arguments['convertWarningsToExceptions'] = $phpunitConfiguration['convertWarningsToExceptions'];
            }
        }
        $arguments['convertDeprecationsToExceptions'] = $arguments['convertDeprecationsToExceptions'] ?? true;
        $arguments['convertErrorsToExceptions'] = $arguments['convertErrorsToExceptions'] ?? true;
        $arguments['convertNoticesToExceptions'] = $arguments['convertNoticesToExceptions'] ?? true;
        $arguments['convertWarningsToExceptions'] = $arguments['convertWarningsToExceptions'] ?? true;
    }
}
