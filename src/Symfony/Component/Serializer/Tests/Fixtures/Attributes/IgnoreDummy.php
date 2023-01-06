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

use Symfony\Component\Serializer\Annotation\Ignore;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class IgnoreDummy
{
    public $notIgnored;
    #[Ignore]
    public $ignored1;
    private $ignored2;

    #[Ignore]
    public function getIgnored2()
    {
        return $this->ignored2;
    }
}
