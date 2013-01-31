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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\HttpCache\Esi;

/**
 * Implements the ESI rendering strategy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EsiRenderingStrategy extends ProxyAwareRenderingStrategy
{
    private $esi;
    private $defaultStrategy;

    /**
     * Constructor.
     *
     * The "fallback" strategy when ESI is not available should always be an
     * instance of DefaultRenderingStrategy (or a class you are using for the
     * default strategy).
     *
     * @param Esi                        $esi             An Esi instance
     * @param RenderingStrategyInterface $defaultStrategy The default strategy to use when ESI is not supported
     */
    public function __construct(Esi $esi, RenderingStrategyInterface $defaultStrategy)
    {
        $this->esi = $esi;
        $this->defaultStrategy = $defaultStrategy;
    }

    /**
     * {@inheritdoc}
     *
     * Note that if the current Request has no ESI capability, this method
     * falls back to use the default rendering strategy.
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
            return $this->defaultStrategy->render($uri, $request, $options);
        }

        if ($uri instanceof ControllerReference) {
            $uri = $this->generateProxyUri($uri, $request);
        }

        $alt = isset($options['alt']) ? $options['alt'] : null;
        if ($alt instanceof ControllerReference) {
            $alt = $this->generateProxyUri($alt, $request);
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
