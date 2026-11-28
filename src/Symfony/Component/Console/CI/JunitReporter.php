<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\CI;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Collects one test case per checked file and emits a JUnit XML report,
 * which most CI servers can display as test results.
 *
 * @see https://github.com/testmoapp/junitxml
 */
class JunitReporter
{
    /**
     * @var list<array{file: string, failure: array{type: string, message: string, details: string}|null}>
     */
    private array $testCases = [];

    public function __construct(
        private OutputInterface $output,
        private string $suiteName = 'lint',
    ) {
    }

    /**
     * Records a file that passed the check.
     */
    public function pass(string $file): void
    {
        $this->testCases[] = ['file' => $file, 'failure' => null];
    }

    /**
     * Records a file that failed the check.
     *
     * @param string|null $details The full failure output; defaults to the message
     * @param string      $type    The kind of failure, e.g. "error" or "deprecation"
     */
    public function fail(string $file, string $message, ?string $details = null, string $type = 'error'): void
    {
        $this->testCases[] = ['file' => $file, 'failure' => ['type' => $type, 'message' => $message, 'details' => $details ?? $message]];
    }

    /**
     * Writes the collected test cases as a JUnit XML document to the output.
     */
    public function write(): void
    {
        $suite = $this->escape($this->suiteName);
        $failures = \count(array_filter($this->testCases, static fn (array $testCase): bool => null !== $testCase['failure']));

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<testsuites>\n";
        $xml .= \sprintf("  <testsuite name=\"%s\" tests=\"%d\" failures=\"%d\" errors=\"0\">\n", $suite, \count($this->testCases), $failures);

        foreach ($this->testCases as ['file' => $file, 'failure' => $failure]) {
            $file = $this->escape($file);
            $xml .= \sprintf('    <testcase name="%s" classname="%s" file="%s"', $file, $suite, $file);

            if (null === $failure) {
                $xml .= "/>\n";

                continue;
            }

            $xml .= \sprintf(">\n      <failure type=\"%s\" message=\"%s\">%s</failure>\n    </testcase>\n", $this->escape($failure['type']), $this->escape($failure['message']), $this->escape($failure['details']));
        }

        $xml .= "  </testsuite>\n</testsuites>";

        $this->output->writeln($xml, OutputInterface::OUTPUT_RAW);
    }

    private function escape(string $value): string
    {
        $value = htmlspecialchars($value, \ENT_XML1 | \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        // Control characters are not allowed in XML 1.0, not even escaped
        return preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', "\u{FFFD}", $value) ?? $value;
    }
}
