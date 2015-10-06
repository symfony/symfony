<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests;

use Symfony\Component\Finder\Finder;

class TranslationSyncStatusTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getTranslationDirectoriesData
     */
    public function testTranslationFileIsNotMissingInCore($dir1, $dir2)
    {
        $finder = new Finder();
        $files = $finder->in($dir1)->files();

        foreach ($files as $file) {
            $this->assertFileExists($dir2.'/'.$file->getFilename(), 'Missing file '.$file->getFilename().' in directory '.$dir2);
        }
    }

    public function getTranslationDirectoriesData()
    {
        $legacyTranslationsDir = $this->getLegacyTranslationsDirectory();
        $coreTranslationsDir = $this->getCoreTranslationsDirectory();

        return array(
            'file-not-missing-in-core' => array($legacyTranslationsDir, $coreTranslationsDir),
            'file-not-added-in-core' => array($coreTranslationsDir, $legacyTranslationsDir),
        );
    }

    public function testFileContentsAreEqual()
    {
        $finder = new Finder();
        $files = $finder->in($this->getLegacyTranslationsDirectory())->files();

        foreach ($files as $file) {
            $coreFile = $this->getCoreTranslationsDirectory().'/'.$file->getFilename();

            $this->assertFileEquals($file->getRealPath(), $coreFile, $file.' and '.$coreFile.' have equal content.');
        }
    }

    private function getLegacyTranslationsDirectory()
    {
        return __DIR__.'/../Resources/translations';
    }

    private function getCoreTranslationsDirectory()
    {
        return __DIR__.'/../Core/Resources/translations';
    }
}
