<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Util;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * A SVN repository containing ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SvnRepository
{
    /**
     * @var string The path to the repository.
     */
    private $path;

    /**
     * @var \SimpleXMLElement
     */
    private $svnInfo;

    /**
     * @var SvnCommit
     */
    private $lastCommit;

    /**
     * Downloads the ICU data for the given version.
     *
     * @param string $url       The URL to download from.
     * @param string $targetDir The directory in which to store the repository.
     *
     * @return SvnRepository The directory where the data is stored.
     *
     * @throws RuntimeException If an error occurs during the download.
     */
    public static function download($url, $targetDir)
    {
        exec('which svn', $output, $result);

        if ($result !== 0) {
            throw new RuntimeException('The command "svn" is not installed.');
        }

        $filesystem = new Filesystem();

        if (!$filesystem->exists($targetDir.'/.svn')) {
            $filesystem->remove($targetDir);
            $filesystem->mkdir($targetDir);

            exec('svn checkout '.$url.' '.$targetDir, $output, $result);

            if ($result !== 0) {
                throw new RuntimeException('The SVN checkout of '.$url.'failed.');
            }
        }

        return new static(realpath($targetDir));
    }

    /**
     * Reads the SVN repository at the given path.
     *
     * @param string $path The path to the repository.
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Returns the path to the repository.
     *
     * @return string The path to the repository.
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Returns the URL of the repository.
     *
     * @return string The URL of the repository.
     */
    public function getUrl()
    {
        return (string) $this->getSvnInfo()->entry->url;
    }

    /**
     * Returns the last commit of the repository.
     *
     * @return SvnCommit The last commit.
     */
    public function getLastCommit()
    {
        if (null === $this->lastCommit) {
            $this->lastCommit = new SvnCommit($this->getSvnInfo()->entry->commit);
        }

        return $this->lastCommit;
    }

    /**
     * Returns information about the SVN repository.
     *
     * @return \SimpleXMLElement The XML result from the "svn info" command.
     *
     * @throws RuntimeException If the "svn info" command failed.
     */
    private function getSvnInfo()
    {
        if (null === $this->svnInfo) {
            exec('svn info --xml '.$this->path, $output, $result);

            $svnInfo = simplexml_load_string(implode("\n", $output));

            if ($result !== 0) {
                throw new RuntimeException('svn info failed');
            }

            $this->svnInfo = $svnInfo;
        }

        return $this->svnInfo;
    }
}
