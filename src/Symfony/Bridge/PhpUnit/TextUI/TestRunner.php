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

use PHPUnit\Runner\Version;
use PHPUnit\TextUI\TestRunner as BaseTestRunner;
use Symfony\Bridge\PhpUnit\SymfonyTestsListenerBC;
use Symfony\Bridge\PhpUnit\SymfonyTestsListener;

/**
 * {@inheritdoc}
 */
class TestRunner extends BaseTestRunner
{
    /**
     * {@inheritdoc}
     */
    protected function handleConfiguration(array &$arguments)
    {
        $arguments['listeners'] = isset($arguments['listeners']) ? $arguments['listeners'] : array();

        if (preg_match('/6\..*(', Version::id())) {
            $arguments['listeners'][] = new SymfonyTestsListener();

        } else {
            $arguments['listeners'][] = new SymfonyTestsListenerBC();
        }

        return parent::handleConfiguration($arguments);
    }
}
