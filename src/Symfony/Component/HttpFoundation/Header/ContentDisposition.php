<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/2/15
 * Time: 10:53 PM
 */

namespace Symfony\Component\HttpFoundation\Header;


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