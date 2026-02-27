<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\ArgumentResolver\ValueResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\ArgumentResolver\ArgumentResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\InputFileValueResolver;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Exception\InvalidFileException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\File\InputFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tests\Fixtures\InvokableWithInputFileTestCommand;
use Symfony\Component\Filesystem\Filesystem;

class InputFileValueResolverTest extends TestCase
{
    private Filesystem $filesystem;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.microtime(true).'.'.mt_rand();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    public function testUnsupportedArgumentType()
    {
        $resolver = new InputFileValueResolver();
        $input = new ArrayInput(['file' => '/some/path'], new InputDefinition([
            new InputArgument('file'),
        ]));

        $command = new class {
            public function __invoke(#[Argument] string $file)
            {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $this->assertSame([], $resolver->resolve('file', $input, $member));
    }

    public function testRequiresExplicitAttribute()
    {
        $resolver = new InputFileValueResolver();
        $input = new ArrayInput(['file' => '/some/path'], new InputDefinition([
            new InputArgument('file'),
        ]));

        // No #[Argument] or #[Option] attribute
        $function = static fn (InputFile $file) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $this->assertSame([], $resolver->resolve('file', $input, $member));
    }

    public function testResolvesInputFileFromPath()
    {
        $path = $this->tempDir.'/test.txt';
        file_put_contents($path, 'test content');

        $resolver = new InputFileValueResolver();
        $input = new ArrayInput(['file' => $path], new InputDefinition([
            new InputArgument('file'),
        ]));

        $command = new class {
            public function __invoke(#[Argument] InputFile $file)
            {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('file', $input, $member);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(InputFile::class, $results[0]);
        $this->assertSame('test content', $results[0]->getContents());
    }

    public function testNullableWithEmptyArgument()
    {
        $resolver = new InputFileValueResolver();
        $input = new ArrayInput(['file' => ''], new InputDefinition([
            new InputArgument('file'),
        ]));

        $command = new class {
            public function __invoke(#[Argument] ?InputFile $file)
            {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('file', $input, $member);

        $this->assertCount(1, $results);
        $this->assertNull($results[0]);
    }

    public function testNullableWithNullArgument()
    {
        $resolver = new InputFileValueResolver();
        $input = new ArrayInput([], new InputDefinition([
            new InputArgument('file', InputArgument::OPTIONAL),
        ]));

        $command = new class {
            public function __invoke(#[Argument] ?InputFile $file)
            {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('file', $input, $member);

        $this->assertCount(1, $results);
        $this->assertNull($results[0]);
    }

    public function testWithOption()
    {
        $path = $this->tempDir.'/test.txt';
        file_put_contents($path, 'test content');

        $resolver = new InputFileValueResolver();
        $input = new ArrayInput(['--file' => $path], new InputDefinition([
            new InputOption('file', null, InputOption::VALUE_REQUIRED),
        ]));

        $command = new class {
            public function __invoke(#[Option] ?InputFile $file = null)
            {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('file', $input, $member);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(InputFile::class, $results[0]);
        $this->assertSame('test content', $results[0]->getContents());
    }

    public function testAlreadyResolvedInputFile()
    {
        $path = $this->tempDir.'/test.txt';
        file_put_contents($path, 'test content');
        $inputFile = InputFile::fromPath($path);

        $resolver = new InputFileValueResolver();
        $input = new ArrayInput(['file' => $inputFile], new InputDefinition([
            new InputArgument('file'),
        ]));

        $command = new class {
            public function __invoke(#[Argument] InputFile $file)
            {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('file', $input, $member);

        $this->assertCount(1, $results);
        $this->assertSame($inputFile, $results[0]);
    }

    public function testInvalidFileThrowsException()
    {
        $resolver = new InputFileValueResolver();
        $input = new ArrayInput(['file' => '/non/existent/file.txt'], new InputDefinition([
            new InputArgument('file'),
        ]));

        $command = new class {
            public function __invoke(#[Argument] InputFile $file)
            {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $this->expectException(InvalidFileException::class);
        $this->expectExceptionMessage('does not exist');

        $resolver->resolve('file', $input, $member);
    }

    public function testArgumentResolverResolvesInputFile()
    {
        $path = $this->tempDir.'/test.txt';
        file_put_contents($path, 'test content');

        $input = new ArrayInput(['file' => $path], new InputDefinition([
            new InputArgument('file'),
        ]));

        $command = new class {
            public function __invoke(#[Argument] InputFile $file): string
            {
                return $file->getContents();
            }
        };

        $resolver = new ArgumentResolver(ArgumentResolver::getDefaultArgumentValueResolvers());
        $arguments = $resolver->getArguments($input, $command);

        $this->assertCount(1, $arguments);
        $this->assertInstanceOf(InputFile::class, $arguments[0]);
        $this->assertSame('test content', $arguments[0]->getContents());
    }

    public function testResolvesInputFileInNonInteractiveMode()
    {
        $tester = new CommandTester(new InvokableWithInputFileTestCommand());
        $tester->execute(['file' => __FILE__], ['interactive' => false]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Filename: InputFileValueResolverTest.php', $tester->getDisplay());
        $this->assertStringContainsString('Valid: yes', $tester->getDisplay());
    }
}
