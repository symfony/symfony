<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization;

interface IncomingMessageClassResolverInterface
{
    /**
     * @return class-string
     */
    public function __invoke(array $encodedEnvelope): string;
}
