<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\AssetsInstallCommand;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Filesystem\Filesystem;

class AssetsInstallCommandTest extends TestCase
{
    #[RequiresMethod(CompletionSuggestions::class, 'suggestDirectories')]
    public function testCompleteTarget()
    {
        $command = new AssetsInstallCommand(new Filesystem(), __DIR__);
        $input = CompletionInput::fromTokens(['assets:install'], 1);
        $input->bind($command->getDefinition());
        $command->complete($input, $suggestions = new CompletionSuggestions());

        $this->assertSame('directories', $suggestions->getPathSuggestion());
    }
}
