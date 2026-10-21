<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Completion;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Completion\CompletionSuggestions;

class CompletionSuggestionsTest extends TestCase
{
    public function testNoPathSuggestionByDefault()
    {
        $this->assertNull((new CompletionSuggestions())->getPathSuggestion());
    }

    public function testSuggestPaths()
    {
        $suggestions = new CompletionSuggestions();

        $this->assertSame($suggestions, $suggestions->suggestPaths());
        $this->assertSame('paths', $suggestions->getPathSuggestion());
    }

    public function testSuggestDirectories()
    {
        $suggestions = new CompletionSuggestions();

        $this->assertSame($suggestions, $suggestions->suggestDirectories());
        $this->assertSame('directories', $suggestions->getPathSuggestion());
    }

    public function testPathsIncludeDirectories()
    {
        $this->assertSame('paths', (new CompletionSuggestions())->suggestDirectories()->suggestPaths()->getPathSuggestion());
        $this->assertSame('paths', (new CompletionSuggestions())->suggestPaths()->suggestDirectories()->getPathSuggestion());
    }
}
