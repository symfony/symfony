<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\Command;

use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\Command\CompressAssetsCommand;
use Symfony\Component\AssetMapper\Compressor\CompressorInterface;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

class CompressAssetsCommandTest extends TestCase
{
    #[RequiresMethod(CompletionSuggestions::class, 'suggestPaths')]
    public function testCompletePaths()
    {
        $command = new CompressAssetsCommand($this->createStub(CompressorInterface::class));
        $input = CompletionInput::fromTokens(['assets:compress'], 1);
        $input->bind($command->getDefinition());
        $command->complete($input, $suggestions = new CompletionSuggestions());

        $this->assertSame('paths', $suggestions->getPathSuggestion());
    }
}
