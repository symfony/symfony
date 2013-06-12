<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Fragment;

use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\FragmentListener;

/**
 * Adds the possibility to generate a fragment URI for a given Controller.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class RoutableFragmentRenderer implements FragmentRendererInterface
{
    private $fragmentPath = '/_fragment';

    /**
     * Sets the fragment path that triggers the fragment listener.
     *
     * @param string $path The path
     *
     * @see FragmentListener
     */
    public function setFragmentPath($path)
    {
        $this->fragmentPath = $path;
    }

    /**
     * Generates a fragment URI for a given controller.
     *
     * @param ControllerReference  $reference         A ControllerReference instance
     * @param Request              $request           A Request instance
     * @param bool                 $includeAttributes whether to include reference attributes into the URI
     *
     * @return string A fragment URI
     */
    protected function generateFragmentUri(ControllerReference $reference, Request $request, $includeAttributes = true)
    {
        // work with copies of query and attributes data
        $renderedAttributes = array('_controller' => $reference->controller);

        if (!isset($reference->attributes['_format'])) {
            $renderedAttributes['_format'] = $request->getRequestFormat();
        }

        if ($includeAttributes) {
            $renderedAttributes = array_merge($renderedAttributes, $reference->attributes);
        }

        $renderedQuery = array_merge($reference->query, array('_path' => http_build_query($renderedAttributes, '', '&')));

        // make sure that logic entities do not end up haphazardly serialized
        parse_str($renderedQuery['_path'], $serializedAttributes);
        if ($serializedAttributes != $renderedAttributes) {
            throw new \LogicException('controller attributes with objects are not supported');
        }

        return $request->getUriForPath($this->fragmentPath.'?'.http_build_query($renderedQuery, '', '&'));
    }
}
