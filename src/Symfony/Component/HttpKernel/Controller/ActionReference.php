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
final class ActionReference
{
    /** @var BundleInterface */
    private $bundle;
    /** @var string */
    private $controller;
    /** @var string */
    private $action;

    public function __construct(BundleInterface $bundle, string $controller, string $action)
    {
        $this->bundle = $bundle;
        $this->controller = $controller;
        $this->action = $action;
    }

    public function getBundle(): BundleInterface
    {
        return $this->bundle;
    }

    public function getController(): string
    {
        return $this->controller;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
