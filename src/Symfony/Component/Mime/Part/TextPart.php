<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Part;

use Symfony\Component\Mime\Encoder\Base64ContentEncoder;
use Symfony\Component\Mime\Encoder\ContentEncoderInterface;
use Symfony\Component\Mime\Encoder\EightBitContentEncoder;
use Symfony\Component\Mime\Encoder\QpContentEncoder;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Header\Headers;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TextPart extends AbstractPart
{
    private static $encoders = [];

    private $body;
    private $charset;
    private $subtype;
    private $disposition;
    private $name;
    private $encoding;

    /**
     * @param resource|string $body
     */
    public function __construct($body, ?string $charset = 'utf-8', string $subtype = 'plain', string $encoding = null)
    {
        parent::__construct();

        if (!\is_string($body) && !\is_resource($body)) {
            throw new \TypeError(sprintf('The body of "%s" must be a string or a resource (got "%s").', self::class, \is_object($body) ? \get_class($body) : \gettype($body)));
        }

        $this->body = $body;
        $this->charset = $charset;
        $this->subtype = $subtype;

        if (null === $encoding) {
            $this->encoding = $this->chooseEncoding();
        } else {
            if ('quoted-printable' !== $encoding && 'base64' !== $encoding && '8bit' !== $encoding) {
                throw new InvalidArgumentException(sprintf('The encoding must be one of "quoted-printable", "base64", or "8bit" ("%s" given).', $encoding));
            }
            $this->encoding = $encoding;
        }
    }

    public function getMediaType(): string
    {
        return 'text';
    }

    public function getMediaSubtype(): string
    {
        return $this->subtype;
    }

    /**
     * @param string $disposition one of attachment, inline, or form-data
     *
     * @return $this
     */
    public function setDisposition(string $disposition)
    {
        $this->disposition = $disposition;

        return $this;
    }

    /**
     * Sets the name of the file (used by FormDataPart).
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getBody(): string
    {
        if (!\is_resource($this->body)) {
            return $this->body;
        }

        if (stream_get_meta_data($this->body)['seekable'] ?? false) {
            rewind($this->body);
        }

        return stream_get_contents($this->body) ?: '';
    }

    public function bodyToString(): string
    {
        return $this->getEncoder()->encodeString($this->getBody(), $this->charset);
    }

    public function bodyToIterable(): iterable
    {
        if (\is_resource($this->body)) {
            if (stream_get_meta_data($this->body)['seekable'] ?? false) {
                rewind($this->body);
            }
            yield from $this->getEncoder()->encodeByteStream($this->body);
        } else {
            yield $this->getEncoder()->encodeString($this->body);
        }
    }

    public function getPreparedHeaders(): Headers
    {
        $headers = parent::getPreparedHeaders();

        $headers->setHeaderBody('Parameterized', 'Content-Type', $this->getMediaType().'/'.$this->getMediaSubtype());
        if ($this->charset) {
            $headers->setHeaderParameter('Content-Type', 'charset', $this->charset);
        }
        if ($this->name && 'form-data' !== $this->disposition) {
            $headers->setHeaderParameter('Content-Type', 'name', $this->name);
        }
        $headers->setHeaderBody('Text', 'Content-Transfer-Encoding', $this->encoding);

        if (!$headers->has('Content-Disposition') && null !== $this->disposition) {
            $headers->setHeaderBody('Parameterized', 'Content-Disposition', $this->disposition);
            if ($this->name) {
                $headers->setHeaderParameter('Content-Disposition', 'name', $this->name);
            }
        }

        return $headers;
    }

    public function asDebugString(): string
    {
        $str = parent::asDebugString();
        if (null !== $this->charset) {
            $str .= ' charset: '.$this->charset;
        }
        if (null !== $this->disposition) {
            $str .= ' disposition: '.$this->disposition;
        }

        return $str;
    }

    private function getEncoder(): ContentEncoderInterface
    {
        if ('8bit' === $this->encoding) {
            return self::$encoders[$this->encoding] ?? (self::$encoders[$this->encoding] = new EightBitContentEncoder());
        }

        if ('quoted-printable' === $this->encoding) {
            return self::$encoders[$this->encoding] ?? (self::$encoders[$this->encoding] = new QpContentEncoder());
        }

        return self::$encoders[$this->encoding] ?? (self::$encoders[$this->encoding] = new Base64ContentEncoder());
    }

    private function chooseEncoding(): string
    {
        if (null === $this->charset) {
            return 'base64';
        }

        return 'quoted-printable';
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        // convert resources to strings for serialization
        if (\is_resource($this->body)) {
            $this->body = $this->getBody();
        }

        $this->_headers = $this->getHeaders();

        return ['_headers', 'body', 'charset', 'subtype', 'disposition', 'name', 'encoding'];
    }

    public function __wakeup()
    {
        $r = new \ReflectionProperty(AbstractPart::class, 'headers');
        $r->setAccessible(true);
        $r->setValue($this, $this->_headers);
        unset($this->_headers);
    }
}
