<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\ErrorRenderer;

use Symfony\Component\ErrorRenderer\Exception\FlattenException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Formats an exception using Serializer for rendering.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class SerializerErrorRenderer
{
    private $serializer;
    private $requestStack;
    private $debug;

    public function __construct(SerializerInterface $serializer, RequestStack $requestStack, bool $debug = true)
    {
        $this->serializer = $serializer;
        $this->requestStack = $requestStack;
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function render(\Throwable $exception): FlattenException
    {
        $format = $this->requestStack->getCurrentRequest()->getPreferredFormat();
        $flattenException = FlattenException::createFromThrowable($exception);

        try {
            return $flattenException->setAsString($this->serializer->serialize($flattenException, $format, ['exception' => $exception]));
        } catch (NotEncodableValueException $_) {
            return (new HtmlErrorHandler($this->debug))->render($exception);
        }
    }
}
