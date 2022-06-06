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

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * TemplateController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class TemplateController
{
    private $twig;

    public function __construct(Environment $twig = null)
    {
        $this->twig = $twig;
    }

    /**
     * Renders a template.
     *
     * @param string    $template   The template name
     * @param int|null  $maxAge     Max age for client caching
     * @param int|null  $sharedAge  Max age for shared (proxy) caching
     * @param bool|null $private    Whether or not caching should apply for client caches only
     * @param array     $context    The context (arguments) of the template
     * @param int       $statusCode The HTTP status code to return with the response. Defaults to 200
     */
    public function templateAction(string $template, int $maxAge = null, int $sharedAge = null, bool $private = null, array $context = [], int $statusCode = 200): Response
    {
        if (null === $this->twig) {
            throw new \LogicException('You cannot use the TemplateController if the Twig Bundle is not available.');
        }

        $response = new Response($this->twig->render($template, $context), $statusCode);

        if ($maxAge) {
            $response->setMaxAge($maxAge);
        }

        if (null !== $sharedAge) {
            $response->setSharedMaxAge($sharedAge);
        }

        if ($private) {
            $response->setPrivate();
        } elseif (false === $private || (null === $private && (null !== $maxAge || null !== $sharedAge))) {
            $response->setPublic();
        }

        return $response;
    }

    public function __invoke(string $template, int $maxAge = null, int $sharedAge = null, bool $private = null, array $context = [], int $statusCode = 200): Response
    {
        return $this->templateAction($template, $maxAge, $sharedAge, $private, $context, $statusCode);
    }
}
