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
    public function resolve(array $encodedEnvelope, array $body): string;
}
