<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fixtures\Bundles\NewXmlBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class NewXmlBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
