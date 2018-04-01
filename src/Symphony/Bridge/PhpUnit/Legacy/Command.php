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
