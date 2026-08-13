<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\CI;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\CI\GitlabCiReporter;
use Symfony\Component\Console\Output\BufferedOutput;

class GitlabCiReporterTest extends TestCase
{
    public function testIsGitlabCiEnvironment()
    {
        $prev = getenv('GITLAB_CI');
        putenv('GITLAB_CI');

        try {
            self::assertFalse(GitlabCiReporter::isGitlabCiEnvironment());
            putenv('GITLAB_CI=true');
            self::assertTrue(GitlabCiReporter::isGitlabCiEnvironment());
        } finally {
            putenv('GITLAB_CI'.($prev ? "=$prev" : ''));
        }
    }

    public function testEmptyReport()
    {
        $reporter = new GitlabCiReporter($buffer = new BufferedOutput());

        $reporter->write();

        self::assertSame([], json_decode(trim($buffer->fetch()), true));
    }

    public function testCollectsIssuesAndEmitsCodeClimateJson()
    {
        $reporter = new GitlabCiReporter($buffer = new BufferedOutput(), 'yaml-lint');

        $reporter->error('Unable to parse', 'config/services.yaml', 12);
        $reporter->warning('Looks suspicious', 'config/routes.yaml', 3);
        $reporter->info('Just a note');

        $reporter->write();

        $report = json_decode(trim($buffer->fetch()), true);
        self::assertCount(3, $report);

        self::assertSame('Unable to parse', $report[0]['description']);
        self::assertSame('yaml-lint', $report[0]['check_name']);
        self::assertSame('major', $report[0]['severity']);
        self::assertSame('config/services.yaml', $report[0]['location']['path']);
        self::assertSame(12, $report[0]['location']['lines']['begin']);
        self::assertSame(16, \strlen($report[0]['fingerprint']));

        self::assertSame('minor', $report[1]['severity']);
        self::assertSame('config/routes.yaml', $report[1]['location']['path']);
        self::assertSame(3, $report[1]['location']['lines']['begin']);

        self::assertSame('info', $report[2]['severity']);
        self::assertSame('', $report[2]['location']['path']);
        self::assertSame(1, $report[2]['location']['lines']['begin']);
    }

    public function testFingerprintIsStableAndUnique()
    {
        $reporter = new GitlabCiReporter($buffer = new BufferedOutput());

        $reporter->error('Same error', 'a.yaml', 1);
        $reporter->error('Same error', 'a.yaml', 1);
        $reporter->error('Same error', 'b.yaml', 1);
        $reporter->write();

        $report = json_decode(trim($buffer->fetch()), true);

        self::assertSame($report[0]['fingerprint'], $report[1]['fingerprint']);
        self::assertNotSame($report[0]['fingerprint'], $report[2]['fingerprint']);
    }
}
