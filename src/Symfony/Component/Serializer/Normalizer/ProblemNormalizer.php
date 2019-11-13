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

use Symfony\Component\ErrorHandler\Exception\FlattenException;

/**
 * Normalizes errors according to the API Problem spec (RFC 7807).
 *
 * @see https://tools.ietf.org/html/rfc7807
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class ProblemNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    private $debug;
    private $defaultContext = [
        'type' => 'https://tools.ietf.org/html/rfc2616#section-10',
        'title' => 'An error occurred',
    ];

    public function __construct(bool $debug = false, array $defaultContext = [])
    {
        $this->debug = $debug;
        $this->defaultContext = $defaultContext + $this->defaultContext;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($exception, string $format = null, array $context = [])
    {
        $context += $this->defaultContext;

        $data = [
            'type' => $context['type'],
            'title' => $context['title'],
            'status' => $context['status'] ?? $exception->getStatusCode(),
            'detail' => $this->debug ? $exception->getMessage() : $exception->getStatusText(),
        ];
        if ($this->debug) {
            $data['class'] = $exception->getClass();
            $data['trace'] = $exception->getTrace();
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null): bool
    {
        return $data instanceof FlattenException;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}
