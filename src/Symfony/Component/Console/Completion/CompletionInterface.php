<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Completion;

/**
 * Signifies that this class is able to provide shell completion values.
 *
 * Implement this interface in your command to customize shell completion.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface CompletionInterface
{
    /**
     * Adds suggestions to $suggestions for the current completion input (e.g. option or argument).
     */
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void;
}
