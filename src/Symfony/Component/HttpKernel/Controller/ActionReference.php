<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * Class for holding bundle + controller name + action name.
 *
 * @author Pavel Batanov <pavel@batanov.me>
 */
class ActionReference
{
    /** @var BundleInterface */
    public $bundle;
    /** @var string */
    public $controller;
    /** @var string */
    public $action;

    public function __construct(BundleInterface $bundle, string $controller, string $action)
    {
        $this->bundle = $bundle;
        $this->controller = $controller;
        $this->action = $action;
    }
}
