<?php

class SyncedTranslationsTest extends PHPUnit_Framework_TestCase
{
    private $securityRootDir;
    private $securityTransDir;
    private $securityCoreTransDir;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->securityRootDir = __DIR__.'/../../../';
        $this->securityTransDir = $this->securityRootDir.'Resources/translations/';
        $this->securityCoreTransDir = $this->securityRootDir.'Core/Resources/translations/';
    }

    public function testTranslationsFoldersAreSynced()
    {
        $this->assertSame(
            $this->getTranslationFilesForDir($this->securityTransDir),
            $this->getTranslationFilesForDir($this->securityCoreTransDir)
        );
    }

    /**
     * @dataProvider translationFilePathsProvider
     */
    public function testTranslationsFilesAreSynced($filePath)
    {
        $originPath = $this->securityCoreTransDir.$filePath;
        $expectedPath = $this->securityTransDir.$filePath;

        $this->assertFileEquals($originPath, $expectedPath, sprintf('"%s" and "%s" translation files should not be out of sync.', $originPath, $expectedPath));
    }

    public function translationFilePathsProvider()
    {
        return array_map(function ($path) {
            return array($path);
        }, $this->getTranslationFilesForDir($this->securityTransDir));
    }

    private function getTranslationFilesForDir($dir)
    {
        $filePaths = array();
        foreach (scandir($dir) as $path) {
            $file = new \SplFileInfo($path);
            if ($file->isDir()) {
                continue;
            }
            if (2 !== substr_count($file->getBasename(), '.') || 0 === preg_match('/\.\w+$/', $file->getBasename())) {
                continue;
            };

            $filePaths[] = $path;
        }

        return $filePaths;
    }
}
