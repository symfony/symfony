<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class LocaleAwareRequestContext implements LocaleAwareInterface
{
    public function __construct(private RequestContext $requestContext, private string $defaultLocale)
    {
    }

    public function setLocale(string $locale): void
    {
        $this->requestContext->setParameter('_locale', $locale);
    }

    public function getLocale(): string
    {
        return $this->requestContext->getParameter('_locale') ?? $this->defaultLocale;
    }
}
