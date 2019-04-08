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

/**
 * {@inheritdoc}
 *
 * @internal
 */
class CommandForV5 extends \PHPUnit_TextUI_Command
{
    /**
     * {@inheritdoc}
     */
    protected function createRunner()
    {
        $listener = new SymfonyTestsListenerForV5();

        $this->arguments['listeners'] = isset($this->arguments['listeners']) ? $this->arguments['listeners'] : array();

        $registeredLocally = false;

        foreach ($this->arguments['listeners'] as $registeredListener) {
            if ($registeredListener instanceof SymfonyTestsListenerForV5) {
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
