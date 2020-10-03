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

/**
 * Resolve denormalization class based on message headers and decoded data.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
interface DecodedAwareTypeResolverInterface
{
    /**
     * @return string the FQDN class to use as deserialization format
     *
     * @param array $body decoded message body
     */
    public function resolve(array $encodedEnvelope, array $body): string;
}
