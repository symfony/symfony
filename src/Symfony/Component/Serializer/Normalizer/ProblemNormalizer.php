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
use Symfony\Component\HttpKernel\Exception\ProblemHttpException;

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
        $debug = $this->debug && ($context['debug'] ?? true);
        $e = $context['exception'] ?? null;

        $data = [
            'type' => $context['type'] ?? $exception->getType() ?? $this->defaultContext['type'],
            'title' => $context['title'] ?? $exception->getTitle() ?? $exception->getStatusText(),
            'status' => $context['status'] ?? $exception->getStatusCode(),
            'detail' => $debug || $e instanceof ProblemHttpException ? $exception->getMessage() : null,
            'instance' => $exception->getInstance(),
        ] + $exception->getExtensions();

        if ($debug) {
            $data['class'] = $exception->getClass();
            $data['trace'] = $exception->getTrace();
        }

        return array_filter($data, static function ($value): bool {
            return null !== $value && '' !== $value;
        });
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
