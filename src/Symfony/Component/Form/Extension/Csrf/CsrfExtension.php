<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Csrf;

use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This extension protects forms by using a CSRF token.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CsrfExtension extends AbstractExtension
{
    private CsrfTokenManagerInterface $tokenManager;
    private ?TranslatorInterface $translator;
    private ?string $translationDomain;

    public function __construct(CsrfTokenManagerInterface $tokenManager, ?TranslatorInterface $translator = null, ?string $translationDomain = null)
    {
        $this->tokenManager = $tokenManager;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
    }

    protected function loadTypeExtensions(): array
    {
        return [
            new Type\FormTypeCsrfExtension($this->tokenManager, true, '_token', $this->translator, $this->translationDomain),
        ];
    }
}
