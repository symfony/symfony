<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

use Symfony\Component\Serializer\Attribute\Groups;

class DefaultGroupsConferenceDummy
{
    public string $name = 'SymfonyCon';

    #[Groups(['detail'])]
    public array $talks = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function getTalks(): array
    {
        return $this->talks;
    }
}
