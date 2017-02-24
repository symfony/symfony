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

if (class_exists('PHPUnit_TextUI_Command')) {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\TestRunner', 'Symfony\Bridge\PhpUnit\TextUI\TestRunner');

    return;
}

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
        $arguments['listeners'] = isset($arguments['listeners']) ? $arguments['listeners'] : array();
        $arguments['listeners'][] = new SymfonyTestsListener();

        return parent::handleConfiguration($arguments);
    }
}
