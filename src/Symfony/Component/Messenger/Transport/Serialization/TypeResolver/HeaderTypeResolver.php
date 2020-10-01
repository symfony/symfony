<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization\TypeResolver;

use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

/**
 * Resolve denormalization class based on a message header value.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
final class HeaderTypeResolver implements TypeResolverInterface
{
    private $headerName;

    public function __construct(string $headerName = 'type')
    {
        $this->headerName = $headerName;
    }

    public function resolve(array $encodedEnvelope): string
    {
        $headers = $encodedEnvelope['headers'] ?? [];
        if (!isset($headers[$this->headerName]) || !$headers[$this->headerName]) {
            throw new MessageDecodingFailedException(sprintf('Encoded envelope does not have a "%s" header.', $this->headerName));
        }

        return $headers[$this->headerName];
    }
}
