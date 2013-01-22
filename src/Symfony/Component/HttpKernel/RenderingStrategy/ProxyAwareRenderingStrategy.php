<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\RenderingStrategy;

use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpFoundation\Request;

/**
 * Adds the possibility to generate a proxy URI for a given Controller.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class ProxyAwareRenderingStrategy implements RenderingStrategyInterface
{
    /**
     * Generates a proxy URI for a given controller.
     *
     * @param ControllerReference  $reference A ControllerReference instance
     * @param Request              $request    A Request instance
     *
     * @return string A proxy URI
     */
    protected function generateProxyUri(ControllerReference $reference, Request $request)
    {
        if (!isset($reference->attributes['_format'])) {
            $reference->attributes['_format'] = $request->getRequestFormat();
        }

        $reference->attributes['_controller'] = $reference->controller;

        $reference->query['path'] = http_build_query($reference->attributes, '', '&');

        return $request->getUriForPath('/_proxy?'.http_build_query($reference->query, '', '&'));
    }
}
