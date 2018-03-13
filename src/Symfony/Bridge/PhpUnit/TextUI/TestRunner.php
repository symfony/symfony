<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\TextUI;

use PHPUnit\TextUI\TestRunner as BaseRunner;
use Symfony\Bridge\PhpUnit\SymfonyTestsListener;

if (class_exists('PHPUnit_Runner_Version') && version_compare(\PHPUnit_Runner_Version::id(), '6.0.0', '<')) {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\TestRunner', 'Symfony\Bridge\PhpUnit\TextUI\TestRunner');
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
            $listener = new SymfonyTestsListener();

            $result = parent::handleConfiguration($arguments);

            $arguments['listeners'] = isset($arguments['listeners']) ? $arguments['listeners'] : array();

            $registeredLocally = false;

            foreach ($arguments['listeners'] as $registeredListener) {
                if ($registeredListener instanceof SymfonyTestsListener) {
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
