<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Data\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Intl\Data\Util\LocaleScanner;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LocaleScannerTest extends TestCase
{
    private string $directory;
    private Filesystem $filesystem;
    private LocaleScanner $scanner;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/LocaleScannerTest/'.mt_rand(1000, 9999);
        $this->filesystem = new Filesystem();
        $this->scanner = new LocaleScanner();

        $this->filesystem->mkdir($this->directory);

        $this->filesystem->touch($this->directory.'/en.txt');
        $this->filesystem->touch($this->directory.'/en_alias.txt');
        $this->filesystem->touch($this->directory.'/en_child.txt');
        $this->filesystem->touch($this->directory.'/de.txt');
        $this->filesystem->touch($this->directory.'/de_alias.txt');
        $this->filesystem->touch($this->directory.'/de_child.txt');
        $this->filesystem->touch($this->directory.'/fr.txt');
        $this->filesystem->touch($this->directory.'/fr_alias.txt');
        $this->filesystem->touch($this->directory.'/fr_child.txt');
        $this->filesystem->touch($this->directory.'/root.txt');
        $this->filesystem->touch($this->directory.'/supplementalData.txt');
        $this->filesystem->touch($this->directory.'/supplementaldata.txt');
        $this->filesystem->touch($this->directory.'/meta.txt');

        file_put_contents($this->directory.'/en_alias.txt', 'en_alias{"%%ALIAS"{"en"}}');
        file_put_contents($this->directory.'/de_alias.txt', 'de_alias{"%%ALIAS"{"de"}}');
        file_put_contents($this->directory.'/fr_alias.txt', 'fr_alias{"%%ALIAS"{"fr"}}');
        file_put_contents($this->directory.'/en_child.txt', 'en_GB{%%Parent{"en"}}');
        file_put_contents($this->directory.'/de_child.txt', 'en_GB{%%Parent{"de"}}');
        file_put_contents($this->directory.'/fr_child.txt', 'en_GB{%%Parent{"fr"}}');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testScanLocales()
    {
        $sortedLocales = ['de', 'de_alias', 'de_child', 'en', 'en_alias', 'en_child', 'fr', 'fr_alias', 'fr_child'];

        $this->assertSame($sortedLocales, $this->scanner->scanLocales($this->directory));
    }

    public function testScanAliases()
    {
        $sortedAliases = ['de_alias' => 'de', 'en_alias' => 'en', 'fr_alias' => 'fr'];

        $this->assertSame($sortedAliases, $this->scanner->scanAliases($this->directory));
    }

    public function testScanParents()
    {
        $sortedParents = ['de_child' => 'de', 'en_child' => 'en', 'fr_child' => 'fr'];

        $this->assertSame($sortedParents, $this->scanner->scanParents($this->directory));
    }
}
