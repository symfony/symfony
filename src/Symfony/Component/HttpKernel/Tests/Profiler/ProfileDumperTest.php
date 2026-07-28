<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Profiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\AjaxDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\ProfileDumper;

class ProfileDumperTest extends TestCase
{
    public function testToArray()
    {
        $profile = $this->createProfile();
        $data = (new ProfileDumper())->toArray($profile);

        $this->assertSame('abc123', $data['token']);
        $this->assertSame('request', $data['type']);
        $this->assertSame('GET', $data['method']);
        $this->assertSame('http://example.com/foo?bar=baz', $data['url']);
        $this->assertSame(500, $data['status_code']);
        $this->assertSame('2026-07-06T10:32:41+00:00', $data['time']);
        $this->assertTrue($data['has_errors']);

        $this->assertArrayHasKey('exception', $data['collectors']);
        $this->assertArrayHasKey('memory', $data['collectors']);
        $this->assertArrayHasKey('request', $data['collectors']);
        $this->assertArrayHasKey('logger', $data['collectors']);

        $exception = $data['collectors']['exception']['exception'];
        $this->assertSame(\RuntimeException::class, $exception['class']);
        $this->assertSame('Something went wrong', $exception['message']);
        $this->assertSame(\LogicException::class, $exception['previous']['class']);

        $this->assertNotFalse(json_encode($data));
    }

    public function testToMarkdown()
    {
        $profile = $this->createProfile();
        $markdown = (new ProfileDumper())->toMarkdown($profile);

        $this->assertStringContainsString('# Profile abc123 — GET http://example.com/foo?bar=baz → 500', $markdown);
        $this->assertStringContainsString('- time: 2026-07-06T10:32:41+00:00 | type: request | errors: yes', $markdown);
        $this->assertStringContainsString('## exception', $markdown);
        $this->assertStringContainsString('Something went wrong', $markdown);
        $this->assertStringContainsString('## memory', $markdown);
        $this->assertStringContainsString('## logger', $markdown);
        $this->assertStringContainsString('An error occurred', $markdown);

        // the exception panel is always rendered first
        $this->assertLessThan(strpos($markdown, '## memory'), strpos($markdown, '## exception'));

        // keys with empty values are kept in the array output but not rendered in Markdown
        $this->assertArrayHasKey('request_files', (new ProfileDumper())->toArray($profile)['collectors']['request']);
        $this->assertStringNotContainsString('request_files', $markdown);
    }

    public function testEmptyPanelsAreOmitted()
    {
        $profile = $this->createProfile();
        $data = (new ProfileDumper())->toArray($profile);

        // the AjaxDataCollector never collects any data
        $this->assertArrayNotHasKey('ajax', $data['collectors']);
    }

    public function testFilterPanels()
    {
        $profile = $this->createProfile();
        $data = (new ProfileDumper())->toArray($profile, ['memory']);

        $this->assertSame(['memory'], array_keys($data['collectors']));
    }

    public function testUnknownPanel()
    {
        $profile = $this->createProfile();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The following panels are not available in this profile: "foobar". Available panels: "memory", "request", "logger", "exception", "ajax".');

        (new ProfileDumper())->toArray($profile, ['foobar']);
    }

    public function testValuesAreTruncated()
    {
        $profile = new Profile('abc123');
        $profile->addCollector($this->roundTrip(new TruncationStubCollector()));

        $data = (new ProfileDumper())->toArray($profile);
        $stub = $data['collectors']['truncation_stub'];

        $this->assertStringEndsWith('… (truncated)', $stub['long_string']);
        $this->assertSame(2048 + \strlen('… (truncated)'), \strlen($stub['long_string']));
        $this->assertSame('…', $stub['deep']['level1']['level2']['level3']);
        $this->assertCount(51, $stub['many_items']);
        $this->assertSame('… 10 more items', $stub['many_items'][50]);
    }

    public function testObjectPropertyNamesUseUmlVisibilityNotation()
    {
        $profile = new Profile('abc123');
        $profile->addCollector($this->roundTrip(new ObjectStubCollector()));

        $data = (new ProfileDumper())->toArray($profile);
        $object = $data['collectors']['object_stub']['object'];

        $this->assertSame(['publicProperty', '#protectedProperty', '-privateProperty', '+dynamicProperty'], array_keys($object));
        $this->assertSame(['date'], array_keys($data['collectors']['object_stub']['virtual']));
        $this->assertStringNotContainsString('\u0000', json_encode($data));
    }

    public function testTruncationCanBeDisabled()
    {
        $profile = new Profile('abc123');
        $profile->addCollector($this->roundTrip(new TruncationStubCollector()));

        $data = (new ProfileDumper(\PHP_INT_MAX, \PHP_INT_MAX, \PHP_INT_MAX, \PHP_INT_MAX))->toArray($profile);
        $stub = $data['collectors']['truncation_stub'];

        $this->assertSame(str_repeat('a', 3000), $stub['long_string']);
        $this->assertSame('value', $stub['deep']['level1']['level2']['level3']['level4']['level5']);
        $this->assertSame(range(1, 60), $stub['many_items']);
    }

    public function testBrokenCollector()
    {
        $profile = new Profile('abc123');
        $profile->addCollector(new BrokenStubCollector());

        $data = (new ProfileDumper())->toArray($profile);

        $this->assertSame(['error' => 'Unable to dump this panel: This collector is broken.'], $data['collectors']['broken_stub']);
    }

    public function testCollectorNotExposingData()
    {
        $profile = new Profile('abc123');
        $profile->addCollector(new NotDumpableStubCollector());

        $dumper = new ProfileDumper();

        $this->assertSame([], $dumper->toArray($profile)['collectors']);
        $this->assertSame(
            \sprintf('The "%s" collector does not expose its data.', NotDumpableStubCollector::class),
            $dumper->toArray($profile, ['not_dumpable_stub'])['collectors']['not_dumpable_stub']
        );
    }

    private function createProfile(): Profile
    {
        $request = Request::create('http://example.com/foo?bar=baz');
        $response = new Response('', 500);
        $exception = new \RuntimeException('Something went wrong', previous: new \LogicException('Root cause'));

        $exceptionCollector = new ExceptionDataCollector();
        $exceptionCollector->collect($request, $response, $exception);

        $memoryCollector = new MemoryDataCollector();
        $memoryCollector->collect($request, $response);
        $memoryCollector->lateCollect();

        $requestCollector = new RequestDataCollector();
        $requestCollector->collect($request, $response);
        $requestCollector->lateCollect();

        $loggerCollector = new LoggerDataCollector(new class implements DebugLoggerInterface {
            public function getLogs(?Request $request = null): array
            {
                return [
                    ['message' => 'An error occurred', 'context' => ['foo' => 'bar'], 'priority' => 400, 'priorityName' => 'ERROR', 'channel' => 'app', 'timestamp' => 1783333961, 'timestamp_rfc3339' => '2026-07-06T10:32:41.000+00:00'],
                    ['message' => 'Some info', 'context' => [], 'priority' => 200, 'priorityName' => 'INFO', 'channel' => 'app', 'timestamp' => 1783333961, 'timestamp_rfc3339' => '2026-07-06T10:32:41.000+00:00'],
                ];
            }

            public function countErrors(?Request $request = null): int
            {
                return 1;
            }

            public function clear(): void
            {
            }
        });
        $loggerCollector->collect($request, $response);
        $loggerCollector->lateCollect();

        $ajaxCollector = new AjaxDataCollector();

        $profile = new Profile('abc123');
        $profile->setUrl('http://example.com/foo?bar=baz');
        $profile->setMethod('GET');
        $profile->setStatusCode(500);
        $profile->setTime(1783333961);
        $profile->setHasErrors(true);

        // serialize/unserialize the collectors to reproduce how profiles are stored
        foreach ([$memoryCollector, $requestCollector, $loggerCollector, $exceptionCollector, $ajaxCollector] as $collector) {
            $profile->addCollector($this->roundTrip($collector));
        }

        return $profile;
    }

    private function roundTrip(DataCollectorInterface $collector): DataCollectorInterface
    {
        return unserialize(serialize($collector));
    }
}

class TruncationStubCollector extends DataCollector
{
    public function __construct()
    {
        $this->data = [
            'long_string' => str_repeat('a', 3000),
            'deep' => ['level1' => ['level2' => ['level3' => ['level4' => ['level5' => 'value']]]]],
            'many_items' => range(1, 60),
        ];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function getName(): string
    {
        return 'truncation_stub';
    }
}

class ObjectStubCollector extends DataCollector
{
    public function __construct()
    {
        $object = new StubObject();
        $object->dynamicProperty = 'dynamic value';

        $this->data = [
            'object' => $this->cloneVar($object),
            // the DateTime caster exposes the date as a virtual property
            'virtual' => $this->cloneVar(new \DateTimeImmutable('2026-07-07')),
        ];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function getName(): string
    {
        return 'object_stub';
    }
}

#[\AllowDynamicProperties]
class StubObject
{
    public string $publicProperty = 'public value';
    protected string $protectedProperty = 'protected value';
    private string $privateProperty = 'private value';
}

class BrokenStubCollector extends DataCollector
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function getName(): string
    {
        return 'broken_stub';
    }

    public function __serialize(): array
    {
        throw new \LogicException('This collector is broken.');
    }
}

class NotDumpableStubCollector implements DataCollectorInterface
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function getName(): string
    {
        return 'not_dumpable_stub';
    }

    public function reset(): void
    {
    }
}
