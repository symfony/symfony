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
use Symfony\Bridge\PhpUnit\SymfonyTestsListener;

/**
 * {@inheritdoc}
 *
 * @internal
 */
class CommandForV6 extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function createRunner(): BaseRunner
    {
        $listener = new SymfonyTestsListener();

        $this->arguments['listeners'] = isset($this->arguments['listeners']) ? $this->arguments['listeners'] : [];

        $registeredLocally = false;

        foreach ($this->arguments['listeners'] as $registeredListener) {
            if ($registeredListener instanceof SymfonyTestsListener) {
                $registeredListener->globalListenerDisabled();
                $registeredLocally = true;
                break;
            }
        }

        if (!$registeredLocally) {
            $this->arguments['listeners'][] = $listener;
        }

        return parent::createRunner();
    }
}
