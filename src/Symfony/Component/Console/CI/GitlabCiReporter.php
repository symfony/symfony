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
 * Collects issues and emits a JSON report in the GitLab Code Quality report
 * format consumed by GitLab CI/CD as a Code Quality artifact
 * (`artifacts:reports:codequality`).
 *
 * @see https://docs.gitlab.com/ci/testing/code_quality/#code-quality-report-format
 *
 * @author Daniel Bohnhardt <github@revoltek.de>
 */
class GitlabCiReporter
{
    /**
     * @var list<array{description: string, check_name: string, fingerprint: string, severity: string, location: array{path: string, lines: array{begin: int}}}>
     */
    private array $issues = [];

    public function __construct(
        private OutputInterface $output,
        private string $checkName = 'lint',
    ) {
    }

    public static function isGitlabCiEnvironment(): bool
    {
        return false !== getenv('GITLAB_CI');
    }

    public function error(string $description, ?string $file = null, ?int $line = null): void
    {
        $this->log('major', $description, $file, $line);
    }

    public function warning(string $description, ?string $file = null, ?int $line = null): void
    {
        $this->log('minor', $description, $file, $line);
    }

    public function info(string $description, ?string $file = null, ?int $line = null): void
    {
        $this->log('info', $description, $file, $line);
    }

    /**
     * Writes the collected issues as a JSON array to the output.
     */
    public function write(): void
    {
        $this->output->writeln(json_encode($this->issues, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }

    private function log(string $severity, string $description, ?string $file, ?int $line): void
    {
        $path = $file ?? '';
        $beginLine = $line ?? 1;

        $this->issues[] = [
            'description' => $description,
            'check_name' => $this->checkName,
            'fingerprint' => hash('xxh3', $this->checkName.'|'.$path.'|'.$beginLine.'|'.$description),
            'severity' => $severity,
            'location' => [
                'path' => $path,
                'lines' => [
                    'begin' => $beginLine,
                ],
            ],
        ];
    }
}
