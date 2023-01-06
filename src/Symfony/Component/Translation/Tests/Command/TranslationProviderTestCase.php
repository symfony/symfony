<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\Provider\FilteringProvider;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
abstract class TranslationProviderTestCase extends TestCase
{
    protected $fs;
    protected $translationAppDir;
    protected $files;
    protected $defaultLocale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultLocale = \Locale::getDefault();
        \Locale::setDefault('en');
        $this->fs = new Filesystem();
        $this->translationAppDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
        $this->fs->mkdir($this->translationAppDir.'/translations');
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->defaultLocale);
        $this->fs->remove($this->translationAppDir);
        parent::tearDown();
    }

    protected function getProviderCollection(ProviderInterface $provider, array $providerNames = ['loco'], array $locales = ['en'], array $domains = ['messages']): TranslationProviderCollection
    {
        $collection = [];

        foreach ($providerNames as $providerName) {
            $collection[$providerName] = new FilteringProvider($provider, $locales, $domains);
        }

        return new TranslationProviderCollection($collection);
    }

    protected function createFile(array $messages = ['note' => 'NOTE'], $targetLanguage = 'en', $fileNamePattern = 'messages.%locale%.xlf', string $xlfVersion = 'xlf12'): string
    {
        if ('xlf12' === $xlfVersion) {
            $transUnits = '';
            foreach ($messages as $key => $value) {
                $transUnits .= <<<XLIFF
<trans-unit id="$key">
    <source>$key</source>
    <target>$value</target>
</trans-unit>
XLIFF;
            }
            $xliffContent = <<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="$targetLanguage" datatype="plaintext" original="file.ext">
        <body>
            $transUnits
        </body>
    </file>
</xliff>
XLIFF;
        } else {
            $units = '';
            foreach ($messages as $key => $value) {
                $units .= <<<XLIFF
<unit id="$key">
  <segment>
    <source>$key</source>
    <target>$value</target>
  </segment>
</unit>
XLIFF;
            }
            $xliffContent = <<<XLIFF
<?xml version="1.0" encoding="utf-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en" trgLang="$targetLanguage">
  <file id="messages.$targetLanguage">
    $units
  </file>
</xliff>
XLIFF;
        }

        $filename = sprintf('%s/%s', $this->translationAppDir.'/translations', str_replace('%locale%', $targetLanguage, $fileNamePattern));
        file_put_contents($filename, $xliffContent);

        $this->files[] = $filename;

        return $filename;
    }
}
