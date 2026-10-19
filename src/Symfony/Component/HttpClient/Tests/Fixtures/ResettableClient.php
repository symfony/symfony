<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Fixtures;

use Symfony\Component\HttpClient\MockHttpClient;

final class ResettableClient extends MockHttpClient
{
    public array $resets = [];

    public function reset(): void
    {
        $this->resets[] = \func_get_args();
        parent::reset();
    }
}
