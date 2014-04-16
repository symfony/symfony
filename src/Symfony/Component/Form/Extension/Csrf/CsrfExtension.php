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

use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * This extension protects forms by using a CSRF token.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CsrfExtension extends AbstractExtension
{
    /**
     * @var CsrfProviderInterface
     */
    private $csrfProvider;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var null|string
     */
    private $translationDomain;

    /**
     * Constructor.
     *
     * @param CsrfProviderInterface $csrfProvider      The CSRF provider
     * @param TranslatorInterface   $translator        The translator for translating error messages.
     * @param null|string           $translationDomain The translation domain for translating.
     */
    public function __construct(CsrfProviderInterface $csrfProvider, TranslatorInterface $translator = null, $translationDomain = null)
    {
        $this->csrfProvider = $csrfProvider;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTypeExtensions()
    {
        return array(
            new Type\FormTypeCsrfExtension($this->csrfProvider, true, '_token', $this->translator, $this->translationDomain),
        );
    }
}
