<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Extension;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use Symfony\Bridge\PhpUnit\Attribute\UseRecord;
use Symfony\Bridge\PhpUnit\HttpRecorder;
use Symfony\Bridge\PhpUnit\Metadata\AttributeReader;
use Symfony\Component\HttpClient\Recorder\RecorderMode;

/**
 * @internal
 */
final class RecorderSubscriber implements PreparationStartedSubscriber
{
    private RecorderMode $mode;
    private ?string $currentTest = null;

    /**
     * @param string|null $directory The directory recordings are resolved against, instead of the directory of each test
     * @param string      $recorder  The value of the SYMFONY_HTTP_RECORDER env var
     */
    public function __construct(
        private AttributeReader $reader,
        private ?string $directory = null,
        string $recorder = '',
    ) {
        if (null === $mode = '' === $recorder ? RecorderMode::Replay : RecorderMode::tryFrom($recorder)) {
            // an invalid value must neither reach the network nor prevent the rest of the extension from booting
            self::warn(\sprintf('Invalid value "%s" for the SYMFONY_HTTP_RECORDER env var, expected one of "%s": HTTP calls are replayed.', $recorder, implode('", "', array_column(RecorderMode::cases(), 'value'))));
        }

        $this->mode = $mode ?? RecorderMode::Replay;
    }

    public static function isAbsolutePath(string $path): bool
    {
        return '' !== $path && ('/' === $path[0] || '\\' === $path[0] || preg_match('#^[a-zA-Z]:[\\\\/]#', $path));
    }

    public static function resolveRecordPath(?string $record, string $testDir, string $className, string $methodName, int|string|null $dataSetName = null, ?string $directory = null): string
    {
        if (null !== $record && '' !== $record) {
            return self::isAbsolutePath($record) ? $record : ($directory ?? $testDir).'/'.$record;
        }

        if (null !== $dataSetName) {
            $methodName .= '@'.preg_replace('/[^\w.-]++/', '_', (string) $dataSetName);
        }

        // a shared directory mirrors the namespace of the test, so that test classes of the same name do not collide
        $className = null !== $directory ? str_replace('\\', '/', $className) : substr(strrchr('\\'.$className, '\\'), 1);

        return ($directory ?? $testDir).'/'.$className.'/'.$methodName.'.har';
    }

    public function notify(PreparationStarted $event): void
    {
        $this->reportMisses();
        HttpRecorder::reset();

        $test = $event->test();

        if (!$test instanceof TestMethod) {
            return;
        }

        $attributes = $this->reader->forClassAndMethod($test->className(), $test->methodName(), UseRecord::class);
        if ([] === $attributes) {
            return;
        }

        // the method-level attribute, when present, takes precedence over the class-level one
        $attribute = $attributes[array_key_last($attributes)];
        $testData = $test->testData();
        $dataSetName = $testData->hasDataFromDataProvider() ? $testData->dataFromDataProvider()->dataSetName() : null;

        $this->currentTest = $test->id();
        HttpRecorder::configure($this->mode, self::resolveRecordPath($attribute->record, \dirname($test->file()), $test->className(), $test->methodName(), $dataSetName, $this->directory));
    }

    /**
     * Reports the requests of the last test that had no recorded response, even when the tested code caught the exception.
     */
    public function reportMisses(): void
    {
        if ($misses = HttpRecorder::getMisses()) {
            self::warn(\sprintf('No recorded response for %s in "%s": run the test with SYMFONY_HTTP_RECORDER=missing to record %s.', implode(', ', $misses), $this->currentTest, 1 === \count($misses) ? 'it' : 'them'));
        }

        $this->currentTest = null;
    }

    private static function warn(string $message): void
    {
        $emitter = EventFacade::emitter();
        method_exists($emitter, 'testRunnerTriggeredPhpunitWarning') ? $emitter->testRunnerTriggeredPhpunitWarning($message) : $emitter->testRunnerTriggeredWarning($message);
    }
}
