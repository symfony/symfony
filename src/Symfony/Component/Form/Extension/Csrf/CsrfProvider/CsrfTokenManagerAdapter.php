<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Csrf\CsrfProvider;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Adapter for using the new token generator with the old interface.
 *
 * @since  2.4
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.4, to be removed in Symfony 3.0.
 */
class CsrfTokenManagerAdapter implements CsrfProviderInterface
{
    /**
     * @var CsrfTokenManagerInterface
     */
    private $tokenManager;

    public function __construct(CsrfTokenManagerInterface $tokenManager)
    {
        $this->tokenManager = $tokenManager;
    }

    public function getTokenManager()
    {
        return $this->tokenManager;
    }

    /**
     * {@inheritdoc}
     */
    public function generateCsrfToken($intention)
    {
        return $this->tokenManager->getToken($intention)->getValue();
    }

    /**
     * {@inheritdoc}
     */
    public function isCsrfTokenValid($intention, $token)
    {
        return $this->tokenManager->isTokenValid(new CsrfToken($intention, $token));
    }
}
