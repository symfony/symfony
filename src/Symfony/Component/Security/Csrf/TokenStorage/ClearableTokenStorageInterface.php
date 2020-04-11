<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\TokenStorage;

use Symfony\Component\Csrf\TokenStorage\ClearableTokenStorageInterface as ComponentClearableTokenStorageInterface;

trigger_deprecation('symfony/security-csrf', '5.1', 'The "%s" class is deprecated, use "%s" instead. The CSRF library has moved to the "symfony/csrf" package, replace the requirement on "symfony/security-csrf" with "symfony/csrf".', ClearableTokenStorageInterface::class, ComponentClearableTokenStorageInterface::class);

class_exists(ComponentClearableTokenStorageInterface::class);

if (false) {
    /**
     * @deprecated since Symfony 5.1, use symfony/csrf instead.
     */
    interface ClearableTokenStorageInterface
    {
    }
}
