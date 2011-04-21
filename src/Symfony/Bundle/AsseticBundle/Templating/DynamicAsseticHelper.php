<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Templating;

use Assetic\Asset\AssetInterface;
use Assetic\Factory\AssetFactory;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\RouterHelper;

/**
 * The dynamic "assetic" templating helper.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class DynamicAsseticHelper extends AsseticHelper
{
    private $routerHelper;

    /**
     * Constructor.
     *
     * @param RouterHelper $routerHelper The router helper
     * @param AssetFactory $factory      The asset factory
     * @param Boolean      $debug        The debug mode
     */
    public function __construct(RouterHelper $routerHelper, AssetFactory $factory, $debug = false)
    {
        $this->routerHelper = $routerHelper;

        parent::__construct($factory, $debug);
    }

    protected function getAssetUrl(AssetInterface $asset, $options = array())
    {
        return $this->routerHelper->generate('_assetic_'.$options['name']);
    }
}
