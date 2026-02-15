<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMapping;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: NestedBankDataResource::class)]
class NestedBankDto
{
    public string $bic;
    #[Map(target: 'bankCode')]
    public string $code;
    #[Map(target: 'bankName')]
    public string $name;
}

