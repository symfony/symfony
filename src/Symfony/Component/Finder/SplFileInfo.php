<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder;

use Symfony\Component\Finder\Exception\RuntimeException;

/**
 * Extends \SplFileInfo to support relative paths
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SplFileInfo extends \SplFileInfo
{
    private $relativePath;
    private $relativePathname;

    /**
     * Constructor.
     *
     * @param string $file             The file name
     * @param string $relativePath     The relative path
     * @param string $relativePathname The relative path name
     */
    public function __construct($file, $relativePath, $relativePathname)
    {
        parent::__construct($file);
        $this->relativePath = $relativePath;
        $this->relativePathname = $relativePathname;
    }

    /**
     * Returns the relative path.
     *
     * @return string the relative path
     */
    public function getRelativePath()
    {
        return $this->relativePath;
    }

    /**
     * Returns the relative path name.
     *
     * @return string the relative path name
     */
    public function getRelativePathname()
    {
        return $this->relativePathname;
    }

    /**
     * Returns the contents of the file.
     *
     * @return string the contents of the file
     *
     * @see file_get_contents()
     *
     * @throws RuntimeException When the content could not be read
     */
    public function getContents()
    {
        $level = error_reporting(0);
        $content = file_get_contents($this->getRealpath());
        error_reporting($level);
        if (false === $content) {
            $error = error_get_last();
            throw new RuntimeException($error['message']);
        }

        return $content;
    }

    /**
     * Writes a string to the file.
     *
     * @param mixed    $data     The string
     * @param integer  $flags    The flags
     * @param mixed    $resource A context resource (@see stream_context_create())
     *
     * @return integer The number of bytes written
     *
     * @throws RuntimeException When the content could not be written
     *
     * @see file_put_contents()
     */
    public function putContents($data, $flags = 0, $resource = null)
    {
        $status = file_put_contents($this->getRealPath(), $data, $flags, $resource);

        if (false === $status) {
            throw new RuntimeException(sprintf("The file '%s' could not be written", $file->getRealPath()));
        }

        return $status;
    }
}
