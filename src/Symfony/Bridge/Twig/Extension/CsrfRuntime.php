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

use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Runtime\EscaperRuntime;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class CsrfRuntime
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function getCsrfToken(string $tokenId): string
    {
        return $this->csrfTokenManager->getToken($tokenId)->getValue();
    }

    public function renderCsrfInputField(Environment $env, string $tokenId, string $fieldName = '_token'): string
    {
        $escaper = $env->getRuntime(EscaperRuntime::class);

        return \sprintf('<input type="hidden" name="%s" value="%s" data-controller="csrf-protection">', $escaper->escape($fieldName), $escaper->escape($this->getCsrfToken($tokenId)));
    }
}
