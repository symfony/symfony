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

/**
 * Implements the ESI rendering strategy.
 *
 * @author Sebastian Krebs <krebs.seb@gmail.com>
 */
class SsiRenderingStrategy extends GeneratorAwareRenderingStrategy
{
    private $defaultStrategy;

    /**
     * Constructor.
     *
     * The "fallback" strategy when ESI is not available should always be an
     * instance of DefaultRenderingStrategy (or a class you are using for the
     * default strategy).
     *
     * @param RenderingStrategyInterface $defaultStrategy The default strategy to use when ESI is not supported
     */
    public function __construct(RenderingStrategyInterface $defaultStrategy)
    {
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
     *  * comment: a comment to add when returning an esi:include tag
     */
    public function render($uri, Request $request = null, array $options = array())
    {
        if (null === $request) {
            return $this->defaultStrategy->render($uri, $request, $options);
        }

        if ($uri instanceof ControllerReference) {
            $uri = $this->generateProxyUri($uri, $request);
        }

        $tag = $this->renderIncludeTag($uri, isset($options['ignore_errors']) ? $options['ignore_errors'] : false, isset($options['comment']) ? $options['comment'] : '');

        return new Response($tag);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'ssi';
    }


    private function renderIncludeTag ($uri, $ignoreErrors = true, $comment = '') {
        $html = sprintf('<!--#include virtual="%s"%s -->',
            $uri,
            $ignoreErrors ? ' fmt="?"' : ''
        );

        if (!empty($comment)) {
            return sprintf("<!-- %s -->\n%s", $comment, $html);
        }

        return $html;
    }
}
