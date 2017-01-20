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
    use PHPUnit\TextUI\Command as PHPUnitCommand;
} else {
    use \PHPUnit_TextUI_Command as PHPUnitCommand;
}

/**
 * {@inheritdoc}
 */
class Command extends PHPUnitCommand
{
    /**
     * {@inheritdoc}
     */
    protected function createRunner()
    {
        return new TestRunner($this->arguments['loader']);
    }
}
