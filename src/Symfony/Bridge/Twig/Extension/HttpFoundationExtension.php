<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\RequestContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for the Symfony HttpFoundation component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since Symfony 4.4
 */
class HttpFoundationExtension extends AbstractExtension
{
    private $urlHelper;

    /**
     * @param UrlHelper $urlHelper
     */
    public function __construct($urlHelper)
    {
        if ($urlHelper instanceof UrlHelper) {
            $this->urlHelper = $urlHelper;

            return;
        }

        if (!$urlHelper instanceof RequestStack) {
            throw new \TypeError(sprintf('The first argument must be an instance of "%s" or an instance of "%s".', UrlHelper::class, RequestStack::class));
        }

        @trigger_error(sprintf('Passing a "%s" instance as the first argument to the "%s" constructor is deprecated since Symfony 4.3, pass a "%s" instance instead.', RequestStack::class, __CLASS__, UrlHelper::class), E_USER_DEPRECATED);

        $requestContext = null;
        if (2 === \func_num_args()) {
            $requestContext = func_get_arg(1);
            if (null !== $requestContext && !$requestContext instanceof RequestContext) {
                throw new \TypeError(sprintf('The second argument must be an instance of "%s".', RequestContext::class));
            }
        }

        $this->urlHelper = new UrlHelper($urlHelper, $requestContext);
    }

    /**
     * {@inheritdoc}
     *
     * @return TwigFunction[]
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('absolute_url', [$this, 'generateAbsoluteUrl']),
            new TwigFunction('relative_path', [$this, 'generateRelativePath']),
        ];
    }

    /**
     * Returns the absolute URL for the given absolute or relative path.
     *
     * This method returns the path unchanged if no request is available.
     *
     * @param string $path The path
     *
     * @return string The absolute URL
     *
     * @see Request::getUriForPath()
     */
    public function generateAbsoluteUrl($path)
    {
        return $this->urlHelper->getAbsoluteUrl($path);
    }

    /**
     * Returns a relative path based on the current Request.
     *
     * This method returns the path unchanged if no request is available.
     *
     * @param string $path The path
     *
     * @return string The relative path
     *
     * @see Request::getRelativeUriForPath()
     */
    public function generateRelativePath($path)
    {
        return $this->urlHelper->getRelativePath($path);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'request';
    }
}
