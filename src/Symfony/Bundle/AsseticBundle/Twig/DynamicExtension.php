<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Twig;

use Assetic\Extension\Twig\AsseticExtension;
use Assetic\Factory\AssetFactory;

/**
 * The dynamic extension is used when use_controllers is enabled.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class DynamicExtension extends AsseticExtension
{
    public function getTokenParsers()
    {
        return array(
            new DynamicTokenParser($this->factory, 'javascripts', 'js/*.js', $this->debug, false, array('package')),
            new DynamicTokenParser($this->factory, 'stylesheets', 'css/*.css', $this->debug, false, array('package')),
            new DynamicTokenParser($this->factory, 'image', 'images/*', $this->debug, true, array('package')),
        );
    }
}
