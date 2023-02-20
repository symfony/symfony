<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Intl\Exception\RuntimeException;
use Symfony\Component\Intl\Util\GitRepository;

/**
 * @group intl-data
 */
class GitRepositoryTest extends TestCase
{
    private $targetDir;

    private const REPO_URL = 'https://github.com/symfony/intl.git';

    /**
     * @before
     *
     * @after
     */
    protected function cleanup()
    {
        $this->targetDir = sys_get_temp_dir().'/GitRepositoryTest/source';

        $fs = new Filesystem();
        $fs->remove($this->targetDir);
    }

    public function testItThrowsAnExceptionIfInitialisedWithNonGitDirectory()
    {
        $this->expectException(RuntimeException::class);

        @mkdir($this->targetDir, 0777, true);

        new GitRepository($this->targetDir);
    }

    public function testItClonesTheRepository()
    {
        $git = GitRepository::download(self::REPO_URL, $this->targetDir);

        $this->assertInstanceOf(GitRepository::class, $git);
        $this->assertDirectoryExists($this->targetDir.'/.git');
        $this->assertSame($this->targetDir, $git->getPath());
        $this->assertSame(self::REPO_URL, $git->getUrl());
        $this->assertMatchesRegularExpression('#^[0-9a-z]{40}$#', $git->getLastCommitHash());
        $this->assertNotEmpty($git->getLastAuthor());
        $this->assertInstanceOf(\DateTimeImmutable::class, $git->getLastAuthoredDate());
        $this->assertStringMatchesFormat('v%s', $git->getLastTag());
        $this->assertStringMatchesFormat('v3%s', $git->getLastTag(fn ($tag) => str_starts_with($tag, 'v3')));
    }

    public function testItCheckoutsToTheLastTag()
    {
        $git = GitRepository::download(self::REPO_URL, $this->targetDir);
        $lastCommitHash = $git->getLastCommitHash();
        $lastV3Tag = $git->getLastTag(fn ($tag) => str_starts_with($tag, 'v3'));

        $git->checkout($lastV3Tag);

        $this->assertNotEquals($lastCommitHash, $git->getLastCommitHash());
    }
}
