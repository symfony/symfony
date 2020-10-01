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
 * Resolve denormalization class based on message headers.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
interface TypeResolverInterface
{
    /**
     * @throws \Symfony\Component\Messenger\Exception\MessageDecodingFailedException
     *
     * @return string the FQDN class to use as deserialization format
     */
    public function resolve(array $encodedEnvelope): string;
}
