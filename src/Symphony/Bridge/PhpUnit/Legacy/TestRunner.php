<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\PhpUnit\Legacy;

/**
 * {@inheritdoc}
 *
 * @internal
 */
class TestRunner extends \PHPUnit_TextUI_TestRunner
{
    /**
     * {@inheritdoc}
     */
    protected function handleConfiguration(array &$arguments)
    {
        $listener = new SymphonyTestsListenerForV5();

        $result = parent::handleConfiguration($arguments);

        $arguments['listeners'] = isset($arguments['listeners']) ? $arguments['listeners'] : array();

        $registeredLocally = false;

        foreach ($arguments['listeners'] as $registeredListener) {
            if ($registeredListener instanceof SymphonyTestsListenerForV5) {
                $registeredListener->globalListenerDisabled();
                $registeredLocally = true;
                break;
            }
        }

        if (!$registeredLocally) {
            $arguments['listeners'][] = $listener;
        }

        return $result;
    }
}
