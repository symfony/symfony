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
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.4, to be removed in 3.0.
 */
class CsrfTokenManagerAdapter implements CsrfProviderInterface
{
    private $tokenManager;

    public function __construct(CsrfTokenManagerInterface $tokenManager)
    {
        $this->tokenManager = $tokenManager;
    }

    public function getTokenManager($triggerDeprecationError = true)
    {
        if ($triggerDeprecationError) {
            @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.4 and will be removed in version 3.0. Use the Symfony\Component\Security\Csrf\CsrfTokenManager class instead.', E_USER_DEPRECATED);
        }

        return $this->tokenManager;
    }

    /**
     * {@inheritdoc}
     */
    public function generateCsrfToken($intention)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.4 and will be removed in version 3.0. Use the Symfony\Component\Security\Csrf\CsrfTokenManager class instead.', E_USER_DEPRECATED);

        return $this->tokenManager->getToken($intention)->getValue();
    }

    /**
     * {@inheritdoc}
     */
    public function isCsrfTokenValid($intention, $token)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.4 and will be removed in version 3.0. Use the Symfony\Component\Security\Csrf\CsrfTokenManager class instead.', E_USER_DEPRECATED);

        return $this->tokenManager->isTokenValid(new CsrfToken($intention, $token));
    }
}
