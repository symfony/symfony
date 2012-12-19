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
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\HttpCache\Esi;

/**
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EsiRenderingStrategy extends GeneratorAwareRenderingStrategy
{
    private $esi;
    private $defaultStrategy;

    public function __construct(Esi $esi, RenderingStrategyInterface $defaultStrategy)
    {
        $this->esi = $esi;
        $this->defaultStrategy = $defaultStrategy;
    }

    /**
     *
     * Note that this method generates an esi:include tag only when both the standalone
     * option is set to true and the request has ESI capability (@see Symfony\Component\HttpKernel\HttpCache\ESI).
     *
     * Available options:
     *
     *  * ignore_errors: true to return an empty string in case of an error
     *  * alt: an alternative URI to execute in case of an error
     *  * comment: a comment to add when returning an esi:include tag
     */
    public function render($uri, Request $request = null, array $options = array())
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

        return $this->esi->renderIncludeTag($uri, $alt, $options['ignore_errors'], isset($options['comment']) ? $options['comment'] : '');
    }

    public function getName()
    {
        return 'esi';
    }
}
