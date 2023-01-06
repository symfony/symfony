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

use Symfony\Component\Serializer\Annotation\SerializedPath;

/**
 * @author Tobias Bönner <tobi@boenner.family>
 */
class SerializedPathDummy
{
    #[SerializedPath('[one][two]')]
    public $three;

    public $seven;

    #[SerializedPath('[three][four]')]
    public function getSeven()
    {
        return $this->seven;
    }
}
