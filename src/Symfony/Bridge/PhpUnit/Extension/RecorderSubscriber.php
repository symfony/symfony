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
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use Symfony\Bridge\PhpUnit\Attribute\UseRecord;
use Symfony\Bridge\PhpUnit\HttpRecorder;
use Symfony\Bridge\PhpUnit\Metadata\AttributeReader;
use Symfony\Component\HttpClient\Recorder\RecorderMode;

final class RecorderSubscriber implements PreparationStartedSubscriber
{
    private array $truncatedPaths = [];

    public function __construct(
        private AttributeReader $reader,
        private string $defaultDirectory,
    ) {
    }

    /**
     * @internal
     */
    public static function isAbsolutePath(string $path): bool
    {
        return '' !== $path && ('/' === $path[0] || '\\' === $path[0] || preg_match('#^[a-zA-Z]:[\\\\/]#', $path));
    }

    /**
     * @internal
     */
    public static function resolveRecordPath(?string $record, string $testDir, string $shortClassName, string $methodName, string $defaultDirectory): string
    {
        if (null === $record || '' === $record) {
            return $testDir.'/'.$shortClassName.'/'.$methodName.'.har';
        }

        if (str_starts_with($record, '@')) {
            return $defaultDirectory.substr($record, 1);
        }

        if (self::isAbsolutePath($record)) {
            return $record;
        }

        return $testDir.'/'.$record;
    }

    /**
     * Truncates a given path only the first time it is resolved in this process, so that a
     * class-level #[UseRecord] pointing at one shared file lets every test of that class
     * append to it instead of restarting from empty on each of them.
     *
     * @internal
     */
    public function shouldTruncate(string $path): bool
    {
        if (isset($this->truncatedPaths[$path])) {
            return false;
        }

        return $this->truncatedPaths[$path] = true;
    }

    public function notify(PreparationStarted $event): void
    {
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

        $currentTestDir = \dirname($test->file());

        /**
         * @see https://regex101.com/r/m2kwWt/1
         */
        $success = preg_match('/(?<className>[^\\\\]*)$/', $test->className(), $matches);
        if (1 !== $success) {
            throw new \LogicException(\sprintf('Failed to extract the class name from test class "%s".', $test->className()));
        }

        // fail closed by default: a miss on replay throws unless the test opts in with recordIfMissing
        $mode = $attribute->mode ?? RecorderMode::Replay;

        $record = self::resolveRecordPath($attribute->record, $currentTestDir, $matches['className'], $test->methodName(), $this->defaultDirectory);

        if (RecorderMode::Record === $mode && $this->shouldTruncate($record)) {
            // recording rewrites the fixture: it starts empty the first time this path is
            // resolved in this process, and every client of every test using it then appends to it
            @unlink($record);
        }

        HttpRecorder::configure($mode, $record, $attribute->recordIfMissing);
    }
}
