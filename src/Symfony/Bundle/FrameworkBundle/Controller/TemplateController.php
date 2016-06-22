<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * TemplateController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplateController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * Renders a template.
     *
     * @param string    $template  The template name
     * @param int|null  $maxAge    Max age for client caching
     * @param int|null  $sharedAge Max age for shared (proxy) caching
     * @param bool|null $private   Whether or not caching should apply for client caches only
     *
     * @return Response A Response instance
     */
    public function templateAction($template, $maxAge = null, $sharedAge = null, $private = null)
    {
        /** @var $response \Symfony\Component\HttpFoundation\Response */
        $response = $this->container->get('templating')->renderResponse($template);

        if ($maxAge) {
            $response->setMaxAge($maxAge);
        }

        if ($sharedAge) {
            $response->setSharedMaxAge($sharedAge);
        }

        if ($private) {
            $response->setPrivate();
        } elseif ($private === false || (null === $private && ($maxAge || $sharedAge))) {
            $response->setPublic();
        }

        return $response;
    }
}
