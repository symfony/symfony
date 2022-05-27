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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Output\CompletionOutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\StreamOutput;

abstract class CompletionOutputTestCase extends TestCase
{
    abstract public function getCompletionOutput(): CompletionOutputInterface;

    abstract public function getExpectedOptionsOutput(): string;

    abstract public function getExpectedValuesOutput(): string;

    public function testOptionsOutput()
    {
        $options = [
            new InputOption('option1', 'o', InputOption::VALUE_NONE),
            new InputOption('negatable', null, InputOption::VALUE_NEGATABLE),
        ];
        $suggestions = new CompletionSuggestions();
        $suggestions->suggestOptions($options);
        $stream = fopen('php://memory', 'rw+');
        $this->getCompletionOutput()->write($suggestions, new StreamOutput($stream));
        fseek($stream, 0);
        $this->assertEquals($this->getExpectedOptionsOutput(), stream_get_contents($stream));
    }

    public function testValuesOutput()
    {
        $suggestions = new CompletionSuggestions();
        $suggestions->suggestValues(['Green', 'Red', 'Yellow']);
        $stream = fopen('php://memory', 'rw+');
        $this->getCompletionOutput()->write($suggestions, new StreamOutput($stream));
        fseek($stream, 0);
        $this->assertEquals($this->getExpectedValuesOutput(), stream_get_contents($stream));
    }
}
