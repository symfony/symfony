<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

use PHPUnit\TextUI\Command as BaseCommand;
use PHPUnit\TextUI\TestRunner as BaseRunner;
use Symfony\Bridge\PhpUnit\TextUI\TestRunner;

/**
 * {@inheritdoc}
 *
 * @internal
 */
class CommandForV6 extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function createRunner(): BaseRunner
    {
        return new TestRunner($this->arguments['loader']);
    }
}
