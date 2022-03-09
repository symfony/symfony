<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Annotation;

/**
 * Indicates that this argument should be deserialized from request body.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class RequestBody
{
    /**
     * @param string|null $format Will be guessed from request if empty, and default to JSON.
     * @param array $context      The serialization context (Useful to set groups / ignore fields).
     */
    public function __construct(public readonly ?string $format = null, public readonly array $context = [])
    {
    }
}
