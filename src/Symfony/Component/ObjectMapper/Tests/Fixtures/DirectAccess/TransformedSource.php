<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: PlainTarget::class, transform: [TransformedTarget::class, 'create'])]
class TransformedSource extends PlainSource
{
}
