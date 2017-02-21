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

if (!class_exists('PHPUnit_TextUI_Command')) {
    return;
}

/**
 * {@inheritdoc}
 */
class Command extends \PHPUnit_TextUI_Command
{
    /**
     * {@inheritdoc}
     */
    protected function createRunner()
    {
        return new TestRunner($this->arguments['loader']);
    }
}
