<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Tests\Fixtures;

use Symphony\Component\Routing\Loader\XmlFileLoader;
use Symphony\Component\Config\Util\XmlUtils;

/**
 * XmlFileLoader with schema validation turned off.
 */
class CustomXmlFileLoader extends XmlFileLoader
{
    protected function loadFile($file)
    {
        return XmlUtils::loadFile($file, function () { return true; });
    }
}
