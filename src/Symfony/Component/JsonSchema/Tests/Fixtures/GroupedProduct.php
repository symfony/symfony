<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

class GroupedProduct
{
    #[Groups(['product:read'])]
    public int $id;
    #[Groups(['product:read', 'product:write'])]
    public string $name;
    #[Groups(['product:write'])]
    public string $internalNote;
    public string $secret;
}
