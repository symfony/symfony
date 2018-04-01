<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\PhpUnit\TextUI;

use PHPUnit\TextUI\TestRunner as BaseRunner;
use Symphony\Bridge\PhpUnit\SymphonyTestsListener;

if (class_exists('PHPUnit_Runner_Version') && version_compare(\PHPUnit_Runner_Version::id(), '6.0.0', '<')) {
    class_alias('Symphony\Bridge\PhpUnit\Legacy\TestRunner', 'Symphony\Bridge\PhpUnit\TextUI\TestRunner');
} else {
    /**
     * {@inheritdoc}
     *
     * @internal
     */
    class TestRunner extends BaseRunner
    {
        /**
         * {@inheritdoc}
         */
        protected function handleConfiguration(array &$arguments)
        {
            $listener = new SymphonyTestsListener();

            $result = parent::handleConfiguration($arguments);

            $arguments['listeners'] = isset($arguments['listeners']) ? $arguments['listeners'] : array();

            $registeredLocally = false;

            foreach ($arguments['listeners'] as $registeredListener) {
                if ($registeredListener instanceof SymphonyTestsListener) {
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
}
