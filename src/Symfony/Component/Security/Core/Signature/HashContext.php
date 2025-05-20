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

final readonly class HashContext implements HashContextInterface
{
    public function __construct(
        private \HashContext $hashContext,
    ) {
    }

    public function update(string $data): void
    {
        hash_update($this->hashContext, ':' . base64_encode($data));
    }

    public function final(): string
    {
        return strtr(base64_encode(hash_final($this->hashContext, true)), '+/=', '-_~');
    }
}
