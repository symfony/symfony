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
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\ValidationFailedException as MessageValidationFailedException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Normalizes errors according to the API Problem spec (RFC 7807).
 *
 * @see https://tools.ietf.org/html/rfc7807
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class ProblemNormalizer implements NormalizerInterface, SerializerAwareInterface, CacheableSupportsMethodInterface
{
    use SerializerAwareTrait;

    public const TITLE = 'title';
    public const TYPE = 'type';
    public const STATUS = 'status';

    public function __construct(
        private bool $debug = false,
        private array $defaultContext = [],
        private ?TranslatorInterface $translator = null,
    ) {
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            FlattenException::class => __CLASS__ === self::class || $this->hasCacheableSupportsMethod(),
        ];
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        if (!$object instanceof FlattenException) {
            throw new InvalidArgumentException(sprintf('The object must implement "%s".', FlattenException::class));
        }

        $data = [];
        $context += $this->defaultContext;
        $debug = $this->debug && ($context['debug'] ?? true);
        $exception = $context['exception'] ?? null;
        if ($exception instanceof HttpExceptionInterface) {
            $exception = $exception->getPrevious();

            if ($exception instanceof PartialDenormalizationException) {
                $trans = $this->translator ? $this->translator->trans(...) : fn ($m, $p) => strtr($m, $p);
                $template = 'This value should be of type {{ type }}.';
                $data = [
                    self::TYPE => 'https://symfony.com/errors/validation',
                    self::TITLE => 'Validation Failed',
                    'violations' => array_map(
                        fn ($e) => [
                            'propertyPath' => $e->getPath(),
                            'title' => $trans($template, [
                                '{{ type }}' => implode('|', $e->getExpectedTypes() ?? ['?']),
                            ], 'validators'),
                            'template' => $template,
                            'parameters' => [
                                '{{ type }}' => implode('|', $e->getExpectedTypes() ?? ['?']),
                            ],
                        ] + ($debug || $e->canUseMessageForUser() ? ['hint' => $e->getMessage()] : []),
                        $exception->getErrors()
                    ),
                ];
                $data['detail'] = implode("\n", array_map(fn ($e) => $e['propertyPath'].': '.$e['title'], $data['violations']));
            } elseif (($exception instanceof ValidationFailedException || $exception instanceof MessageValidationFailedException)
                && $this->serializer instanceof NormalizerInterface
                && $this->serializer->supportsNormalization($exception->getViolations(), $format, $context)
            ) {
                $data = $this->serializer->normalize($exception->getViolations(), $format, $context);
            }
        }

        $data = [
            self::TYPE => $data[self::TYPE] ?? $context[self::TYPE] ?? 'https://tools.ietf.org/html/rfc2616#section-10',
            self::TITLE => $data[self::TITLE] ?? $context[self::TITLE] ?? 'An error occurred',
            self::STATUS => $context[self::STATUS] ?? $object->getStatusCode(),
            'detail' => $data['detail'] ?? ($debug ? $object->getMessage() : $object->getStatusText()),
        ] + $data;
        if ($debug) {
            $data['class'] = $object->getClass();
            $data['trace'] = $object->getTrace();
        }

        return $data;
    }

    /**
     * @param array $context
     */
    public function supportsNormalization(mixed $data, string $format = null /* , array $context = [] */): bool
    {
        return $data instanceof FlattenException;
    }

    /**
     * @deprecated since Symfony 6.3, use "getSupportedTypes()" instead
     */
    public function hasCacheableSupportsMethod(): bool
    {
        trigger_deprecation('symfony/serializer', '6.3', 'The "%s()" method is deprecated, implement "%s::getSupportedTypes()" instead.', __METHOD__, get_debug_type($this));

        return true;
    }
}
