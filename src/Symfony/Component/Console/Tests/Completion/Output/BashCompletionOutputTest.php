<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Completion\Output;

use Symfony\Component\Console\Completion\Output\BashCompletionOutput;
use Symfony\Component\Console\Completion\Output\CompletionOutputInterface;

class BashCompletionOutputTest extends CompletionOutputTestCase
{
    public function getCompletionOutput(): CompletionOutputInterface
    {
        return new BashCompletionOutput();
    }

    public function getExpectedOptionsOutput(): string
    {
        return "--option1\n--negatable\n--no-negatable".\PHP_EOL;
    }

    public function getExpectedValuesOutput(): string
    {
        return "Green\nRed\nYellow".\PHP_EOL;
    }
}
