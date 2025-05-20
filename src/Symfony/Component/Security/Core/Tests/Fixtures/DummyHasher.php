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

namespace Symfony\Component\Security\Core\Tests\Fixtures;

use Symfony\Component\Security\Core\Signature\HashContextInterface;
use Symfony\Component\Security\Core\Signature\HasherInterface;

class DummyHasher implements HasherInterface
{
    public function init(): HashContextInterface
    {
        return new DummyHashContext();
    }

    public function hmac(string $data, string $key): string
    {
        return sprintf('HMAC(%s,%s)', $data, $key);
    }
}
