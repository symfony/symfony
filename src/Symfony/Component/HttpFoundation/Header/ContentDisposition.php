<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Header;


/**
 * Represents a Content-Disposition header.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Yosef Deray <yderay@gmail.com>
 */
class ContentDisposition
{
    const DISPOSITION_ATTACHMENT = 'attachment';
    const DISPOSITION_INLINE = 'inline';

    private $disposition;
    private $filename;
    private $filenameFallback;

    public function __construct($disposition, $filename, $filenameFallback = '')
    {
        if (!in_array($disposition, array(self::DISPOSITION_ATTACHMENT, self::DISPOSITION_INLINE))) {
            throw new \InvalidArgumentException(sprintf('The disposition must be either "%s" or "%s".', self::DISPOSITION_ATTACHMENT, self::DISPOSITION_INLINE));
        }

        if ('' == $filenameFallback) {
            $filenameFallback = $filename;
        }

        // filenameFallback is not ASCII.
        if (!preg_match('/^[\x20-\x7e]*$/', $filenameFallback)) {
            throw new \InvalidArgumentException('The filename fallback must only contain ASCII characters.');
        }

        // percent characters aren't safe in fallback.
        if (false !== strpos($filenameFallback, '%')) {
            throw new \InvalidArgumentException('The filename fallback cannot contain the "%" character.');
        }

        // path separators aren't allowed in either.
        if (false !== strpos($filename, '/') || false !== strpos($filename, '\\') || false !== strpos($filenameFallback, '/') || false !== strpos($filenameFallback, '\\')) {
            throw new \InvalidArgumentException('The filename and the fallback cannot contain the "/" and "\\" characters.');
        }

        $this->filename = $filename;
        $this->disposition = $disposition;
        $this->filenameFallback = $filenameFallback;
    }

    public function __toString()
    {
        $output = sprintf(
            '%s; filename="%s"',
            $this->disposition,
            str_replace('"', '\\"', $this->filenameFallback)
        );

        if ($this->filename !== $this->filenameFallback) {
            $output .= sprintf("; filename*=utf-8''%s", rawurlencode($this->filename));
        }

        return $output;
    }

    public static function fromString($header)
    {
        preg_match('/(?<disposition>attachment|inline);\s?filename="(?<filenameFallback>[\x20-\x7e]*)"(?:;\s?filename\*=utf-8\'\'(?<filename>[\x20-\x7e]*))?/', $header, $matches);
        $matches = array_merge(array(
            'disposition' => self::DISPOSITION_ATTACHMENT,
            'filename' => '',
            'filenameFallback' => ''
        ), $matches);
        return new static($matches['disposition'], $matches['filename'], $matches['filenameFallback']);
    }

    /**
     * @return string
     */
    public function getDisposition()
    {
        return $this->disposition;
    }

    /**
     * @return mixed
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return string
     */
    public function getFilenameFallback()
    {
        return $this->filenameFallback;
    }
}