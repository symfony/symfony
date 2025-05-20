<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Security\Core\Signature;

final readonly class Hasher implements HasherInterface
{
    public function __construct(
        private string $algo,
    ) {
    }

    public function init(): HashContextInterface
    {
        return new HashContext(hash_init($this->algo));
    }

    public function hmac(string $data, string $key): string
    {
        return strtr(base64_encode(hash_hmac($this->algo, $data, $key, true)), '+/=', '-_~');
    }
}
