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
    /**
     * @var string
     */
    private $field;

    public function __construct(string $field = 'type')
    {
        $this->field = $field;
    }

    public function resolve(array $encodedEnvelope): string
    {
        $headers = $encodedEnvelope['headers'] ?? [];
        if (!isset($headers[$this->field]) || !$headers[$this->field]) {
            throw new MessageDecodingFailedException(sprintf('Encoded envelope does not have a "%s" header.', $this->field));
        }

        return $headers[$this->field];
    }
}
