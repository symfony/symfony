<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class PruneScriptTest extends TestCase
{
    private const LOCALES = ['de', 'en', 'en_001', 'en_GB', 'en_IN', 'fil', 'fr', 'fr_BE', 'fr_CA', 'hi', 'hi_Latn', 'nb', 'nn', 'no', 'zh', 'zh_Hant', 'zh_Hant_HK'];

    private string $directory;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/PruneScriptTest/'.random_int(1000, 9999);
        $this->filesystem = new Filesystem();

        $this->filesystem->copy(\dirname(__DIR__).'/Resources/bin/prune', $this->directory.'/bin/prune');
        $this->filesystem->dumpFile($this->directory.'/data/version.txt', "79.1\n");
        $this->filesystem->dumpFile($this->directory.'/data/locales/meta.php', '<?php return '.var_export([
            'Locales' => self::LOCALES,
            'Parents' => ['en_GB' => 'en_001', 'en_IN' => 'en_001', 'hi_Latn' => 'en_IN', 'nb' => 'no', 'nn' => 'no', 'zh_Hant' => 'root'],
            'Aliases' => ['tl' => 'fil'],
        ], true).';');

        foreach ([...self::LOCALES, 'meta', 'root'] as $locale) {
            $this->filesystem->dumpFile($this->directory.'/data/languages/'.$locale.('de' === $locale ? '.php.gz' : '.php'), '');
        }
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testPrune()
    {
        $this->assertSame(0, $this->prune('fr_CA', 'nb', 'zh_Hant_HK'));
        $this->assertSame(['en', 'fr', 'fr_CA', 'meta', 'nb', 'no', 'root', 'zh', 'zh_Hant', 'zh_Hant_HK'], $this->getLanguageFiles());
        $this->assertFileExists($this->directory.'/data/locales/meta.php');
        $this->assertFileExists($this->directory.'/data/version.txt');
    }

    public function testPruneKeepsRegionalVariantsAndTheirParents()
    {
        $this->assertSame(0, $this->prune('fr', 'hi'));
        $this->assertSame(['en', 'en_001', 'en_IN', 'fr', 'fr_BE', 'fr_CA', 'hi', 'hi_Latn', 'meta', 'root'], $this->getLanguageFiles());
    }

    public function testPruneKeepsCompressedFiles()
    {
        $this->assertSame(0, $this->prune('de'));
        $this->assertSame(['de', 'en', 'meta', 'root'], $this->getLanguageFiles());
        $this->assertFileExists($this->directory.'/data/languages/de.php.gz');
    }

    public function testPruneResolvesAliases()
    {
        $this->assertSame(0, $this->prune('tl'));
        $this->assertSame(['en', 'fil', 'meta', 'root'], $this->getLanguageFiles());
    }

    public function testPruneFailsOnUnknownLocales()
    {
        $this->assertSame(1, $this->prune('fr', 'xx'));
        $this->assertCount(\count(self::LOCALES) + 2, $this->getLanguageFiles());
    }

    public function testPruneFailsWithoutLocales()
    {
        $this->assertSame(1, $this->prune());
        $this->assertCount(\count(self::LOCALES) + 2, $this->getLanguageFiles());
    }

    private function prune(string ...$locales): int
    {
        exec(implode(' ', array_map(escapeshellarg(...), [\PHP_BINARY, $this->directory.'/bin/prune', ...$locales])).' 2>&1', $output, $status);

        return $status;
    }

    private function getLanguageFiles(): array
    {
        $locales = array_map(static fn ($file) => basename(basename($file, '.gz'), '.php'), glob($this->directory.'/data/languages/*'));
        sort($locales);

        return $locales;
    }
}
