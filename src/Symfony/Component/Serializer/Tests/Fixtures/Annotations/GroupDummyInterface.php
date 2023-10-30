<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Annotations;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface GroupDummyInterface
{
    /**
     * @Groups({"a", "name_converter"})
     */
    public function getSymfony();
}
