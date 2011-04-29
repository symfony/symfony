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

use Assetic\Extension\Twig\AsseticExtension as BaseAsseticExtension;
use Assetic\Factory\AssetFactory;

/**
 * Assetic extension.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class AsseticExtension extends BaseAsseticExtension
{
    private $useController;

    public function __construct(AssetFactory $factory, $useController = false, $functions = array())
    {
        parent::__construct($factory, $functions);

        $this->useController = $useController;
    }

    public function getTokenParsers()
    {
        return array(
            new AsseticTokenParser($this->factory, 'javascripts', 'js/*.js', false, array('package')),
            new AsseticTokenParser($this->factory, 'stylesheets', 'css/*.css', false, array('package')),
            new AsseticTokenParser($this->factory, 'image', 'images/*', true, array('package')),
        );
    }

    public function getNodeVisitors()
    {
        return array(new AsseticNodeVisitor());
    }

    public function getGlobals()
    {
        $globals = parent::getGlobals();
        $globals['assetic']['use_controller'] = $this->useController;

        return $globals;
    }
}
