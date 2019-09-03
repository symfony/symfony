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
        $this->arguments['listeners'] = isset($this->arguments['listeners']) ? $this->arguments['listeners'] : array();

        $registeredLocally = false;

        foreach ($this->arguments['listeners'] as $registeredListener) {
            if ($registeredListener instanceof SymfonyTestsListenerForV5) {
                $registeredListener->globalListenerDisabled();
                $registeredLocally = true;
                break;
            }
        }

        if (isset($this->arguments['configuration'])) {
            $configuration = $this->arguments['configuration'];
            if (!$configuration instanceof \PHPUnit_Util_Configuration) {
                $configuration = \PHPUnit_Util_Configuration::getInstance($this->arguments['configuration']);
            }
            foreach ($configuration->getListenerConfiguration() as $registeredListener) {
                if ('Symfony\Bridge\PhpUnit\SymfonyTestsListener' === ltrim($registeredListener['class'], '\\')) {
                    $registeredLocally = true;
                    break;
                }
            }
        }

        if (!$registeredLocally) {
            $this->arguments['listeners'][] = new SymfonyTestsListenerForV5();
        }

        return parent::createRunner();
    }
}
