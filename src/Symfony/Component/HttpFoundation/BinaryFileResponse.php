<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * BinaryFileResponse represents an HTTP response delivering a file.
 *
 * @author Niklas Fiekas <niklas.fiekas@tu-clausthal.de>
 * @author stealth35 <stealth35-php@live.fr>
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 * @author Sergey Linnik <linniksa@gmail.com>
 */
class BinaryFileResponse extends Response
{
    protected static $trustXSendfileTypeHeader = false;

    /**
     * @var File
     */
    protected $file;
    protected $offset = 0;
    protected $maxlen = -1;
    protected $deleteFileAfterSend = false;
    protected $chunkSize = 16 * 1024;

    /**
     * @param \SplFileInfo|string $file               The file to stream
     * @param int                 $status             The response status code
     * @param array               $headers            An array of response headers
     * @param bool                $public             Files are public by default
     * @param string|null         $contentDisposition The type of Content-Disposition to set automatically with the filename
     * @param bool                $autoEtag           Whether the ETag header should be automatically set
     * @param bool                $autoLastModified   Whether the Last-Modified header should be automatically set
     */
    public function __construct($file, int $status = 200, array $headers = [], bool $public = true, ?string $contentDisposition = null, bool $autoEtag = false, bool $autoLastModified = true)
    {
        parent::__construct(null, $status, $headers);

        $this->setFile($file, $contentDisposition, $autoEtag, $autoLastModified);

        if ($public) {
            $this->setPublic();
        }
    }

    /**
     * @param \SplFileInfo|string $file               The file to stream
     * @param int                 $status             The response status code
     * @param array               $headers            An array of response headers
     * @param bool                $public             Files are public by default
     * @param string|null         $contentDisposition The type of Content-Disposition to set automatically with the filename
     * @param bool                $autoEtag           Whether the ETag header should be automatically set
     * @param bool                $autoLastModified   Whether the Last-Modified header should be automatically set
     *
     * @return static
     *
     * @deprecated since Symfony 5.2, use __construct() instead.
     */
    public static function create($file = null, int $status = 200, array $headers = [], bool $public = true, ?string $contentDisposition = null, bool $autoEtag = false, bool $autoLastModified = true)
    {
        trigger_deprecation('symfony/http-foundation', '5.2', 'The "%s()" method is deprecated, use "new %s()" instead.', __METHOD__, static::class);

        return new static($file, $status, $headers, $public, $contentDisposition, $autoEtag, $autoLastModified);
    }

    /**
     * Sets the file to stream.
     *
     * @param \SplFileInfo|string $file The file to stream
     *
     * @return $this
     *
     * @throws FileException
     */
    public function setFile($file, ?string $contentDisposition = null, bool $autoEtag = false, bool $autoLastModified = true)
    {
        if (!$file instanceof File) {
            if ($file instanceof \SplFileInfo) {
                $file = new File($file->getPathname());
            } else {
                $file = new File((string) $file);
            }
        }

        if (!$file->isReadable()) {
            throw new FileException('File must be readable.');
        }

        $this->file = $file;

        if ($autoEtag) {
            $this->setAutoEtag();
        }

        if ($autoLastModified) {
            $this->setAutoLastModified();
        }

        if ($contentDisposition) {
            $this->setContentDisposition($contentDisposition);
        }

        return $this;
    }

    /**
     * Gets the file.
     *
     * @return File
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Sets the response stream chunk size.
     *
     * @return $this
     */
    public function setChunkSize(int $chunkSize): self
    {
        if ($chunkSize < 1 || $chunkSize > \PHP_INT_MAX) {
            throw new \LogicException('The chunk size of a BinaryFileResponse cannot be less than 1 or greater than PHP_INT_MAX.');
        }

        $this->chunkSize = $chunkSize;

        return $this;
    }

    /**
     * Automatically sets the Last-Modified header according the file modification date.
     *
     * @return $this
     */
    public function setAutoLastModified()
    {
        $this->setLastModified(\DateTime::createFromFormat('U', $this->file->getMTime()));

        return $this;
    }

    /**
     * Automatically sets the ETag header according to the checksum of the file.
     *
     * @return $this
     */
    public function setAutoEtag()
    {
        $this->setEtag(base64_encode(hash_file('sha256', $this->file->getPathname(), true)));

        return $this;
    }

    /**
     * Sets the Content-Disposition header with the given filename.
     *
     * @param string $disposition      ResponseHeaderBag::DISPOSITION_INLINE or ResponseHeaderBag::DISPOSITION_ATTACHMENT
     * @param string $filename         Optionally use this UTF-8 encoded filename instead of the real name of the file
     * @param string $filenameFallback A fallback filename, containing only ASCII characters. Defaults to an automatically encoded filename
     *
     * @return $this
     */
    public function setContentDisposition(string $disposition, string $filename = '', string $filenameFallback = '')
    {
        if ('' === $filename) {
            $filename = $this->file->getFilename();
        }

        if ('' === $filenameFallback && (!preg_match('/^[\x20-\x7e]*$/', $filename) || str_contains($filename, '%'))) {
            $encoding = mb_detect_encoding($filename, null, true) ?: '8bit';

            for ($i = 0, $filenameLength = mb_strlen($filename, $encoding); $i < $filenameLength; ++$i) {
                $char = mb_substr($filename, $i, 1, $encoding);

                if ('%' === $char || \ord($char) < 32 || \ord($char) > 126) {
                    $filenameFallback .= '_';
                } else {
                    $filenameFallback .= $char;
                }
            }
        }

        $dispositionHeader = $this->headers->makeDisposition($disposition, $filename, $filenameFallback);
        $this->headers->set('Content-Disposition', $dispositionHeader);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(Request $request)
    {
        if ($this->isInformational() || $this->isEmpty()) {
            parent::prepare($request);

            $this->maxlen = 0;

            return $this;
        }

        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', $this->file->getMimeType() ?: 'application/octet-stream');
        }

        parent::prepare($request);

        $this->offset = 0;
        $this->maxlen = -1;

        if (false === $fileSize = $this->file->getSize()) {
            return $this;
        }
        $this->headers->remove('Transfer-Encoding');
        $this->headers->set('Content-Length', $fileSize);

        if (!$this->headers->has('Accept-Ranges')) {
            // Only accept ranges on safe HTTP methods
            $this->headers->set('Accept-Ranges', $request->isMethodSafe() ? 'bytes' : 'none');
        }

        if (self::$trustXSendfileTypeHeader && $request->headers->has('X-Sendfile-Type')) {
            // Use X-Sendfile, do not send any content.
            $type = $request->headers->get('X-Sendfile-Type');
            $path = $this->file->getRealPath();
            // Fall back to scheme://path for stream wrapped locations.
            if (false === $path) {
                $path = $this->file->getPathname();
            }
            if ('x-accel-redirect' === strtolower($type)) {
                // Do X-Accel-Mapping substitutions.
                // @link https://github.com/rack/rack/blob/main/lib/rack/sendfile.rb
                // @link https://mattbrictson.com/blog/accelerated-rails-downloads
                if (!$request->headers->has('X-Accel-Mapping')) {
                    throw new \LogicException('The "X-Accel-Mapping" header must be set when "X-Sendfile-Type" is set to "X-Accel-Redirect".');
                }
                $parts = HeaderUtils::split($request->headers->get('X-Accel-Mapping'), ',=');
                foreach ($parts as $part) {
                    [$pathPrefix, $location] = $part;
                    if (substr($path, 0, \strlen($pathPrefix)) === $pathPrefix) {
                        $path = $location.substr($path, \strlen($pathPrefix));
                        // Only set X-Accel-Redirect header if a valid URI can be produced
                        // as nginx does not serve arbitrary file paths.
                        $this->headers->set($type, $path);
                        $this->maxlen = 0;
                        break;
                    }
                }
            } else {
                $this->headers->set($type, $path);
                $this->maxlen = 0;
            }
        } elseif ($request->headers->has('Range') && $request->isMethod('GET')) {
            // Process the range headers.
            if (!$request->headers->has('If-Range') || $this->hasValidIfRangeHeader($request->headers->get('If-Range'))) {
                $range = $request->headers->get('Range');

                if (str_starts_with($range, 'bytes=')) {
                    [$start, $end] = explode('-', substr($range, 6), 2) + [1 => 0];

                    $end = ('' === $end) ? $fileSize - 1 : (int) $end;

                    if ('' === $start) {
                        $start = $fileSize - $end;
                        $end = $fileSize - 1;
                    } else {
                        $start = (int) $start;
                    }

                    if ($start <= $end) {
                        $end = min($end, $fileSize - 1);
                        if ($start < 0 || $start > $end) {
                            $this->setStatusCode(416);
                            $this->headers->set('Content-Range', sprintf('bytes */%s', $fileSize));
                        } elseif ($end - $start < $fileSize - 1) {
                            $this->maxlen = $end < $fileSize ? $end - $start + 1 : -1;
                            $this->offset = $start;

                            $this->setStatusCode(206);
                            $this->headers->set('Content-Range', sprintf('bytes %s-%s/%s', $start, $end, $fileSize));
                            $this->headers->set('Content-Length', $end - $start + 1);
                        }
                    }
                }
            }
        }

        if ($request->isMethod('HEAD')) {
            $this->maxlen = 0;
        }

        return $this;
    }

    private function hasValidIfRangeHeader(?string $header): bool
    {
        if ($this->getEtag() === $header) {
            return true;
        }

        if (null === $lastModified = $this->getLastModified()) {
            return false;
        }

        return $lastModified->format('D, d M Y H:i:s').' GMT' === $header;
    }

    /**
     * {@inheritdoc}
     */
    public function sendContent()
    {
        try {
            if (!$this->isSuccessful()) {
                return parent::sendContent();
            }

            if (0 === $this->maxlen) {
                return $this;
            }

            $out = fopen('php://output', 'w');
            $file = fopen($this->file->getPathname(), 'r');

            ignore_user_abort(true);

            if (0 !== $this->offset) {
                fseek($file, $this->offset);
            }

            $length = $this->maxlen;
            while ($length && !feof($file)) {
                $read = $length > $this->chunkSize || 0 > $length ? $this->chunkSize : $length;

                if (false === $data = fread($file, $read)) {
                    break;
                }
                while ('' !== $data) {
                    $read = fwrite($out, $data);
                    if (false === $read || connection_aborted()) {
                        break 2;
                    }
                    if (0 < $length) {
                        $length -= $read;
                    }
                    $data = substr($data, $read);
                }
            }

            fclose($out);
            fclose($file);
        } finally {
            if ($this->deleteFileAfterSend && is_file($this->file->getPathname())) {
                unlink($this->file->getPathname());
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException when the content is not null
     */
    public function setContent(?string $content)
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on a BinaryFileResponse instance.');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        return false;
    }

    /**
     * Trust X-Sendfile-Type header.
     */
    public static function trustXSendfileTypeHeader()
    {
        self::$trustXSendfileTypeHeader = true;
    }

    /**
     * If this is set to true, the file will be unlinked after the request is sent
     * Note: If the X-Sendfile header is used, the deleteFileAfterSend setting will not be used.
     *
     * @return $this
     */
    public function deleteFileAfterSend(bool $shouldDelete = true)
    {
        $this->deleteFileAfterSend = $shouldDelete;

        return $this;
    }
}
