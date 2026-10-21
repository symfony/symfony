<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests\Command;

use PHPUnit\Framework\Attributes\RequiresMethod;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\ErrorHandler\Command\ErrorDumpCommand;
use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;

class ErrorDumpCommandTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/error_pages';

        $fs = new Filesystem();
        $fs->remove($this->tmpDir);
    }

    public function testDumpPages()
    {
        $tester = $this->getCommandTester($this->createStub(KernelInterface::class), []);
        $tester->execute([
            'path' => $this->tmpDir,
        ]);

        $this->assertFileExists($this->tmpDir.\DIRECTORY_SEPARATOR.'404.html');
        $this->assertStringContainsString('Error 404', file_get_contents($this->tmpDir.\DIRECTORY_SEPARATOR.'404.html'));
    }

    public function testDumpPagesOnlyForGivenStatusCodes()
    {
        $fs = new Filesystem();
        $fs->mkdir($this->tmpDir);
        $fs->touch($this->tmpDir.\DIRECTORY_SEPARATOR.'test.html');

        $tester = $this->getCommandTester($this->createStub(KernelInterface::class));
        $tester->execute([
            'path' => $this->tmpDir,
            'status-codes' => ['400', '500'],
        ]);

        $this->assertFileExists($this->tmpDir.\DIRECTORY_SEPARATOR.'test.html');
        $this->assertFileDoesNotExist($this->tmpDir.\DIRECTORY_SEPARATOR.'404.html');

        $this->assertFileExists($this->tmpDir.\DIRECTORY_SEPARATOR.'400.html');
        $this->assertStringContainsString('Error 400', file_get_contents($this->tmpDir.\DIRECTORY_SEPARATOR.'400.html'));
    }

    public function testForceRemovalPages()
    {
        $fs = new Filesystem();
        $fs->mkdir($this->tmpDir);
        $fs->touch($this->tmpDir.\DIRECTORY_SEPARATOR.'test.html');

        $tester = $this->getCommandTester($this->createStub(KernelInterface::class));
        $tester->execute([
            'path' => $this->tmpDir,
            '--force' => true,
        ]);

        $this->assertFileDoesNotExist($this->tmpDir.\DIRECTORY_SEPARATOR.'test.html');
        $this->assertFileExists($this->tmpDir.\DIRECTORY_SEPARATOR.'404.html');
    }

    #[RequiresMethod(CompletionSuggestions::class, 'suggestDirectories')]
    public function testCompletePath()
    {
        $command = new ErrorDumpCommand(new Filesystem(), $this->createStub(ErrorRendererInterface::class));
        $input = CompletionInput::fromTokens(['error:dump'], 1);
        $input->bind($command->getDefinition());
        $command->complete($input, $suggestions = new CompletionSuggestions());

        $this->assertSame('directories', $suggestions->getPathSuggestion());
    }

    private function getCommandTester(KernelInterface $kernel): CommandTester
    {
        $errorRenderer = $this->createStub(ErrorRendererInterface::class);
        $errorRenderer
            ->method('render')
            ->willReturnCallback(static function (HttpException $e) {
                $exception = FlattenException::createFromThrowable($e);
                $exception->setAsString(\sprintf('<html><body>Error %s</body></html>', $e->getStatusCode()));

                return $exception;
            })
        ;

        $entrypointLookup = $this->createStub(EntrypointLookupInterface::class);

        $application = new Application($kernel);
        $application->addCommand(new ErrorDumpCommand(
            new Filesystem(),
            $errorRenderer,
            $entrypointLookup,
        ));

        return new CommandTester($application->find('error:dump'));
    }
}
