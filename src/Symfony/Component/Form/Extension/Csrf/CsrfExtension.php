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

use Symfony\Component\Form\Extension\Csrf\Type;
use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Security\Csrf\CsrfTokenGeneratorInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * This extension protects forms by using a CSRF token.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CsrfExtension extends AbstractExtension
{
    /**
     * @var CsrfTokenGeneratorInterface
     */
    private $tokenGenerator;

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
     * @param CsrfTokenGeneratorInterface $tokenGenerator    The CSRF token generator
     * @param TranslatorInterface         $translator        The translator for translating error messages
     * @param null|string                 $translationDomain The translation domain for translating
     */
    public function __construct(CsrfTokenGeneratorInterface $tokenGenerator, TranslatorInterface $translator = null, $translationDomain = null)
    {
        $this->tokenGenerator = $tokenGenerator;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
    }

    /**
     * {@inheritDoc}
     */
    protected function loadTypeExtensions()
    {
        return array(
            new Type\FormTypeCsrfExtension($this->tokenGenerator, true, '_token', $this->translator, $this->translationDomain),
        );
    }
}
