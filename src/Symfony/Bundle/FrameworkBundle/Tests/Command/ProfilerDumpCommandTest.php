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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\ProfilerDumpCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class ProfilerDumpCommandTest extends TestCase
{
    private string $storageDir;
    private Profiler $profiler;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/sf_profiler_dump_command_test'.uniqid();
        $this->profiler = new Profiler(new FileProfilerStorage('file:'.$this->storageDir));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->storageDir);
    }

    public function testDumpByToken()
    {
        $this->saveProfile('aaaaaa', 'http://example.com/foo');
        $this->saveProfile('bbbbbb', 'http://example.com/bar');

        $tester = $this->createCommandTester();
        $tester->execute(['token' => 'aaaaaa']);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('# Profile aaaaaa — GET http://example.com/foo → 200', $tester->getDisplay());
        $this->assertStringContainsString('## memory', $tester->getDisplay());
    }

    public function testDumpLatest()
    {
        $this->saveProfile('aaaaaa', 'http://example.com/foo');
        $this->saveProfile('bbbbbb', 'http://example.com/bar');
        // profiles of other types are ignored when resolving the "latest" token
        $this->saveProfile('cccccc', 'http://example.com/some-command', virtualType: 'command');

        $tester = $this->createCommandTester();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('# Profile bbbbbb', $tester->getDisplay());
    }

    public function testDumpLatestOfCommandType()
    {
        $this->saveProfile('aaaaaa', 'http://example.com/foo');
        $this->saveProfile('cccccc', 'http://example.com/some-command', virtualType: 'command');
        $this->saveProfile('bbbbbb', 'http://example.com/bar');

        $tester = $this->createCommandTester();
        $tester->execute(['--type' => 'command']);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('# Profile cccccc', $tester->getDisplay());
    }

    public function testDumpLatestWithFilters()
    {
        $this->saveProfile('aaaaaa', 'http://example.com/foo', statusCode: 500);
        $this->saveProfile('bbbbbb', 'http://example.com/bar');

        $tester = $this->createCommandTester();
        $tester->execute(['--status-code' => '500']);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('# Profile aaaaaa', $tester->getDisplay());
    }

    public function testDumpLatestWhenNoProfiles()
    {
        $tester = $this->createCommandTester();

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('No profiles found for type "request".', $tester->getDisplay());
    }

    public function testDumpUnknownToken()
    {
        $this->saveProfile('aaaaaa', 'http://example.com/foo');

        $tester = $this->createCommandTester();

        $this->assertSame(1, $tester->execute(['token' => 'zzzzzz']));
        $this->assertStringContainsString('No profile found for token "zzzzzz".', $tester->getDisplay());
    }

    public function testDumpUnknownPanel()
    {
        $this->saveProfile('aaaaaa', 'http://example.com/foo');

        $tester = $this->createCommandTester();

        $this->assertSame(1, $tester->execute(['token' => 'aaaaaa', '--panel' => ['foobar']]));
        $this->assertStringContainsString('Available panels: "memory".', $tester->getDisplay());
    }

    public function testDumpPanel()
    {
        $this->saveProfile('aaaaaa', 'http://example.com/foo');

        $tester = $this->createCommandTester();
        $tester->execute(['token' => 'aaaaaa', '--panel' => ['memory']]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('## memory', $tester->getDisplay());
    }

    public function testDumpFull()
    {
        $profile = new Profile('aaaaaa');
        $profile->setUrl('http://example.com/foo');
        $profile->setMethod('GET');
        $profile->setStatusCode(200);
        $profile->setTime(time());
        $profile->addCollector(new LongValueStubCollector());
        $this->profiler->saveProfile($profile);

        $tester = $this->createCommandTester();
        $tester->execute(['token' => 'aaaaaa']);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('… (truncated)', $tester->getDisplay());

        $tester->execute(['token' => 'aaaaaa', '--full' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString(str_repeat('a', 3000), $tester->getDisplay());
        $this->assertStringNotContainsString('… (truncated)', $tester->getDisplay());
    }

    public function testDumpJsonFormat()
    {
        $this->saveProfile('aaaaaa', 'http://example.com/foo');

        $tester = $this->createCommandTester();
        $tester->execute(['token' => 'aaaaaa', '--format' => 'json']);

        $tester->assertCommandIsSuccessful();
        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame('aaaaaa', $data['token']);
        $this->assertSame('http://example.com/foo', $data['url']);
        $this->assertArrayHasKey('memory', $data['collectors']);
    }

    public function testInvalidFormat()
    {
        $tester = $this->createCommandTester();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supported formats are "md", "json".');

        $tester->execute(['--format' => 'xml']);
    }

    public function testListProfiles()
    {
        $this->saveProfile('aaaaaa', 'http://example.com/foo', statusCode: 500);
        $this->saveProfile('bbbbbb', 'http://example.com/bar', method: 'POST');
        $this->saveProfile('cccccc', 'http://example.com/some-command', virtualType: 'command');

        $tester = $this->createCommandTester();
        $tester->execute(['--list' => true]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        $this->assertStringContainsString('| TOKEN | TIME | METHOD | URL | STATUS | TYPE | ERRORS |', $display);
        $this->assertStringContainsString('| aaaaaa |', $display);
        $this->assertStringContainsString('| bbbbbb |', $display);
        // profiles of all types are listed by default
        $this->assertStringContainsString('| cccccc |', $display);
        // the newest profile is listed first
        $this->assertLessThan(strpos($display, '| aaaaaa |'), strpos($display, '| cccccc |'));
    }

    public function testListProfilesWithFilters()
    {
        $this->saveProfile('aaaaaa', 'http://example.com/foo');
        $this->saveProfile('bbbbbb', 'http://example.com/bar', method: 'POST');
        $this->saveProfile('cccccc', 'http://example.com/some-command', virtualType: 'command');

        $tester = $this->createCommandTester();
        $tester->execute(['--list' => true, '--method' => 'POST']);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('| bbbbbb |', $tester->getDisplay());
        $this->assertStringNotContainsString('| aaaaaa |', $tester->getDisplay());

        $tester->execute(['--list' => true, '--type' => 'command']);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('| cccccc |', $tester->getDisplay());
        $this->assertStringNotContainsString('| aaaaaa |', $tester->getDisplay());

        $tester->execute(['--list' => true, '--limit' => 1]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('| cccccc |', $tester->getDisplay());
        $this->assertStringNotContainsString('| bbbbbb |', $tester->getDisplay());
    }

    public function testListProfilesJsonFormat()
    {
        $this->saveProfile('aaaaaa', 'http://example.com/foo');

        $tester = $this->createCommandTester();
        $tester->execute(['--list' => true, '--format' => 'json']);

        $tester->assertCommandIsSuccessful();
        $data = json_decode($tester->getDisplay(), true);
        $this->assertCount(1, $data);
        $this->assertSame('aaaaaa', $data[0]['token']);
        $this->assertSame('request', $data[0]['type']);
    }

    public function testComplete()
    {
        $this->saveProfile('aaaaaa', 'http://example.com/foo');

        $tester = new CommandCompletionTester(new ProfilerDumpCommand($this->profiler));

        $this->assertSame(['latest', 'aaaaaa'], $tester->complete(['']));
        $this->assertSame(['md', 'json'], $tester->complete(['--format', '']));
        $this->assertSame(['request', 'command'], $tester->complete(['--type', '']));
    }

    private function createCommandTester(): CommandTester
    {
        return new CommandTester(new ProfilerDumpCommand($this->profiler));
    }

    private function saveProfile(string $token, string $url, string $method = 'GET', int $statusCode = 200, ?string $virtualType = null): Profile
    {
        $collector = new MemoryDataCollector();
        $collector->collect(Request::create($url), new Response());

        $profile = new Profile($token);
        $profile->setUrl($url);
        $profile->setMethod($method);
        $profile->setStatusCode($statusCode);
        $profile->setTime(time());
        $profile->setVirtualType($virtualType);
        $profile->addCollector($collector);

        $this->profiler->saveProfile($profile);

        return $profile;
    }
}

class LongValueStubCollector extends DataCollector
{
    public function __construct()
    {
        $this->data = ['long_string' => str_repeat('a', 3000)];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function getName(): string
    {
        return 'long_value_stub';
    }
}
