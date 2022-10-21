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
use Symfony\Component\HttpFoundation\UrlHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for the Symfony HttpFoundation component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class HttpFoundationExtension extends AbstractExtension
{
    private UrlHelper $urlHelper;

    public function __construct(UrlHelper $urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('absolute_url', $this->generateAbsoluteUrl(...)),
            new TwigFunction('relative_path', $this->generateRelativePath(...)),
        ];
    }

    /**
     * Returns the absolute URL for the given absolute or relative path.
     *
     * This method returns the path unchanged if no request is available.
     *
     * @see Request::getUriForPath()
     */
    public function generateAbsoluteUrl(string $path): string
    {
        return $this->urlHelper->getAbsoluteUrl($path);
    }

    /**
     * Returns a relative path based on the current Request.
     *
     * This method returns the path unchanged if no request is available.
     *
     * @see Request::getRelativeUriForPath()
     */
    public function generateRelativePath(string $path): string
    {
        return $this->urlHelper->getRelativePath($path);
    }
}
