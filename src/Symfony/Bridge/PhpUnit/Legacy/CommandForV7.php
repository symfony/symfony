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

use PHPUnit\TextUI\Command as BaseCommand;
use PHPUnit\TextUI\TestRunner as BaseRunner;
use PHPUnit\Util\Configuration;
use Symfony\Bridge\PhpUnit\SymfonyTestsListener;

/**
 * @internal
 */
class CommandForV7 extends BaseCommand
{
    protected function createRunner(): BaseRunner
    {
        $this->arguments['listeners'] ?? $this->arguments['listeners'] = [];

        $registeredLocally = false;

        foreach ($this->arguments['listeners'] as $registeredListener) {
            if ($registeredListener instanceof SymfonyTestsListener) {
                $registeredListener->globalListenerDisabled();
                $registeredLocally = true;
                break;
            }
        }

        if (isset($this->arguments['configuration'])) {
            $configuration = $this->arguments['configuration'];
            if (!$configuration instanceof Configuration) {
                $configuration = Configuration::getInstance($this->arguments['configuration']);
            }
            foreach ($configuration->getListenerConfiguration() as $registeredListener) {
                if ('Symfony\Bridge\PhpUnit\SymfonyTestsListener' === ltrim($registeredListener['class'], '\\')) {
                    $registeredLocally = true;
                    break;
                }
            }
        }

        if (!$registeredLocally) {
            $this->arguments['listeners'][] = new SymfonyTestsListener();
        }

        return parent::createRunner();
    }
}
