<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface as DeprecatedMimeTypeGuesserInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

/**
 * Normalizes an {@see \SplFileInfo} object to a data URI.
 * Denormalizes a data URI to a {@see \SplFileObject} object.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DataUriNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    private const SUPPORTED_TYPES = [
        \SplFileInfo::class => true,
        \SplFileObject::class => true,
        File::class => true,
    ];

    /**
     * @var MimeTypeGuesserInterface|null
     */
    private $mimeTypeGuesser;

    /**
     * @param MimeTypeGuesserInterface|null $mimeTypeGuesser
     */
    public function __construct($mimeTypeGuesser = null)
    {
        if ($mimeTypeGuesser instanceof DeprecatedMimeTypeGuesserInterface) {
            @trigger_error(sprintf('Passing a %s to "%s()" is deprecated since Symfony 4.3, pass a "%s" instead.', DeprecatedMimeTypeGuesserInterface::class, __METHOD__, MimeTypeGuesserInterface::class), \E_USER_DEPRECATED);
        } elseif (null === $mimeTypeGuesser) {
            if (class_exists(MimeTypes::class)) {
                $mimeTypeGuesser = MimeTypes::getDefault();
            } elseif (class_exists(MimeTypeGuesser::class)) {
                @trigger_error(sprintf('Passing null to "%s()" to use a default MIME type guesser without Symfony Mime installed is deprecated since Symfony 4.3. Try running "composer require symfony/mime".', __METHOD__), \E_USER_DEPRECATED);
                $mimeTypeGuesser = MimeTypeGuesser::getInstance();
            }
        } elseif (!$mimeTypeGuesser instanceof MimeTypes) {
            throw new \TypeError(sprintf('Argument 1 passed to "%s()" must be an instance of "%s" or null, "%s" given.', __METHOD__, MimeTypes::class, \is_object($mimeTypeGuesser) ? \get_class($mimeTypeGuesser) : \gettype($mimeTypeGuesser)));
        }

        $this->mimeTypeGuesser = $mimeTypeGuesser;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function normalize($object, $format = null, array $context = [])
    {
        if (!$object instanceof \SplFileInfo) {
            throw new InvalidArgumentException('The object must be an instance of "\SplFileInfo".');
        }

        $mimeType = $this->getMimeType($object);
        $splFileObject = $this->extractSplFileObject($object);

        $data = '';

        $splFileObject->rewind();
        while (!$splFileObject->eof()) {
            $data .= $splFileObject->fgets();
        }

        if ('text' === explode('/', $mimeType, 2)[0]) {
            return sprintf('data:%s,%s', $mimeType, rawurlencode($data));
        }

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($data));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof \SplFileInfo;
    }

    /**
     * {@inheritdoc}
     *
     * Regex adapted from Brian Grinstead code.
     *
     * @see https://gist.github.com/bgrins/6194623
     *
     * @throws InvalidArgumentException
     * @throws NotNormalizableValueException
     *
     * @return \SplFileInfo
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        if (null === $data || !preg_match('/^data:([a-z0-9][a-z0-9\!\#\$\&\-\^\_\+\.]{0,126}\/[a-z0-9][a-z0-9\!\#\$\&\-\^\_\+\.]{0,126}(;[a-z0-9\-]+\=[a-z0-9\-]+)?)?(;base64)?,[a-z0-9\!\$\&\\\'\,\(\)\*\+\,\;\=\-\.\_\~\:\@\/\?\%\s]*\s*$/i', $data)) {
            throw new NotNormalizableValueException('The provided "data:" URI is not valid.');
        }

        try {
            switch ($type) {
                case File::class:
                    return new File($data, false);

                case 'SplFileObject':
                case 'SplFileInfo':
                    return new \SplFileObject($data);
            }
        } catch (\RuntimeException $exception) {
            throw new NotNormalizableValueException($exception->getMessage(), $exception->getCode(), $exception);
        }

        throw new InvalidArgumentException(sprintf('The class parameter "%s" is not supported. It must be one of "SplFileInfo", "SplFileObject" or "Symfony\Component\HttpFoundation\File\File".', $type));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return isset(self::SUPPORTED_TYPES[$type]);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }

    /**
     * Gets the mime type of the object. Defaults to application/octet-stream.
     */
    private function getMimeType(\SplFileInfo $object): string
    {
        if ($object instanceof File) {
            return $object->getMimeType();
        }

        if ($this->mimeTypeGuesser instanceof DeprecatedMimeTypeGuesserInterface && $mimeType = $this->mimeTypeGuesser->guess($object->getPathname())) {
            return $mimeType;
        }

        if ($this->mimeTypeGuesser && $mimeType = $this->mimeTypeGuesser->guessMimeType($object->getPathname())) {
            return $mimeType;
        }

        return 'application/octet-stream';
    }

    /**
     * Returns the \SplFileObject instance associated with the given \SplFileInfo instance.
     */
    private function extractSplFileObject(\SplFileInfo $object): \SplFileObject
    {
        if ($object instanceof \SplFileObject) {
            return $object;
        }

        return $object->openFile();
    }
}
