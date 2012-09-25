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
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\AbstractExtension;

/**
 * This extension protects forms by using a CSRF token.
 */
class CsrfExtension extends AbstractExtension
{
    private $csrfProvider;

    /**
     * Constructor.
     *
     * @param CsrfProviderInterface $csrfProvider The CSRF provider
     */
    public function __construct(CsrfProviderInterface $csrfProvider)
    {
        $this->csrfProvider = $csrfProvider;
    }

    /**
     * {@inheritDoc}
     */
    protected function loadTypeExtensions()
    {
        return array(
            new Type\FormTypeCsrfExtension($this->csrfProvider),
        );
    }
}
