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

use Symfony\Bridge\PhpUnit\SymfonyTestsListener;

if (!class_exists('PHPUnit_TextUI_TestRunner')) {
    return;
}

/**
 * {@inheritdoc}
 */
class TestRunner extends \PHPUnit_TextUI_TestRunner
{
    /**
     * {@inheritdoc}
     */
    protected function handleConfiguration(array &$arguments)
    {
        $listener = new SymfonyTestsListener();

        $result = parent::handleConfiguration($arguments);

        $arguments['listeners'] = isset($arguments['listeners']) ? $arguments['listeners'] : array();

        if (!array_filter($arguments['listeners'], function ($listener) { return $listener instanceof SymfonyTestsListener; })) {
            $arguments['listeners'][] = $listener;
        }

        return $result;
    }
}
