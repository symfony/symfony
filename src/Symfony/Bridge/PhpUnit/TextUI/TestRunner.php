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

if (class_exists('PHPUnit\Framework\Test')) {
    use PHPUnit\TextUI\TestRunner as PHPUnitTestRunner;
} else {
    use \PHPUnit_TextUI_TestRunner as PHPUnitTestRunner;
}
use Symfony\Bridge\PhpUnit\SymfonyTestsListener;

/**
 * {@inheritdoc}
 */
class TestRunner extends PHPUnitTestRunner
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
