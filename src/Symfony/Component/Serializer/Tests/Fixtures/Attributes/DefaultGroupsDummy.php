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

class DefaultGroupsDummy
{
    public string $noGroup = 'noGroup';

    #[Groups(['custom'])]
    public string $customGroup = 'customGroup';

    #[Groups(['Default'])]
    public string $defaultGroup = 'defaultGroup';

    #[Groups(['DefaultGroupsDummy'])]
    public string $classGroup = 'classGroup';

    public function getNoGroup(): string
    {
        return $this->noGroup;
    }

    public function getCustomGroup(): string
    {
        return $this->customGroup;
    }

    public function getDefaultGroup(): string
    {
        return $this->defaultGroup;
    }

    public function getClassGroup(): string
    {
        return $this->classGroup;
    }
}
