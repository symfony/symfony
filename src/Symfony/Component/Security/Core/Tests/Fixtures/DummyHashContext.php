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

class DummyHashContext implements HashContextInterface
{
    private string $data = '';

    public function update(string $data): void
    {
        $this->data .= ':' . $data;
    }

    public function final(): string
    {
        return sprintf('HASH(%s)', $this->data);
    }
}
