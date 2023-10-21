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

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
interface ClearableTokenStorageInterface extends TokenStorageInterface
{
    /**
     * Removes all CSRF tokens.
     *
     * @return void
     */
    public function clear();
}
