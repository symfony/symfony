<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Csrf;

use Symphony\Component\Form\AbstractExtension;
use Symphony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symphony\Component\Translation\TranslatorInterface;

/**
 * This extension protects forms by using a CSRF token.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CsrfExtension extends AbstractExtension
{
    private $tokenManager;
    private $translator;
    private $translationDomain;

    /**
     * @param CsrfTokenManagerInterface $tokenManager      The CSRF token manager
     * @param TranslatorInterface       $translator        The translator for translating error messages
     * @param null|string               $translationDomain The translation domain for translating
     */
    public function __construct(CsrfTokenManagerInterface $tokenManager, TranslatorInterface $translator = null, string $translationDomain = null)
    {
        $this->tokenManager = $tokenManager;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTypeExtensions()
    {
        return array(
            new Type\FormTypeCsrfExtension($this->tokenManager, true, '_token', $this->translator, $this->translationDomain),
        );
    }
}
