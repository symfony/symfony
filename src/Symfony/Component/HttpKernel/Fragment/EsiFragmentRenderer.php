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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\HttpCache\Esi;

/**
 * Implements the ESI rendering strategy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EsiFragmentRenderer extends RoutableFragmentRenderer
{
    private $esi;
    private $inlineStrategy;

    /**
     * Constructor.
     *
     * The "fallback" strategy when ESI is not available should always be an
     * instance of InlineFragmentRenderer.
     *
     * @param Esi                       $esi            An Esi instance
     * @param FragmentRendererInterface $inlineStrategy The inline strategy to use when ESI is not supported
     */
    public function __construct(Esi $esi, FragmentRendererInterface $inlineStrategy)
    {
        $this->esi = $esi;
        $this->inlineStrategy = $inlineStrategy;
    }

    /**
     * {@inheritdoc}
     *
     * Note that if the current Request has no ESI capability, this method
     * falls back to use the inline rendering strategy.
     *
     * Additional available options:
     *
     *  * alt: an alternative URI to render in case of an error
     *  * comment: a comment to add when returning an esi:include tag
     *
     * @see Symfony\Component\HttpKernel\HttpCache\ESI
     */
    public function render($uri, Request $request, array $options = array())
    {
        if (!$this->esi->hasSurrogateEsiCapability($request)) {
            return $this->inlineStrategy->render($uri, $request, $options);
        }

        if ($uri instanceof ControllerReference) {
            $uri = $this->generateFragmentUri($uri, $request);
        }

        $alt = isset($options['alt']) ? $options['alt'] : null;
        if ($alt instanceof ControllerReference) {
            $alt = $this->generateFragmentUri($alt, $request);
        }

        $tag = $this->esi->renderIncludeTag($uri, $alt, isset($options['ignore_errors']) ? $options['ignore_errors'] : false, isset($options['comment']) ? $options['comment'] : '');

        return new Response($tag);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'esi';
    }
}
