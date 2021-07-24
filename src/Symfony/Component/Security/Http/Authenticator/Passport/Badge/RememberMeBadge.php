<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport\Badge;

use Symfony\Component\Security\Http\EventListener\CheckRememberMeConditionsListener;

/**
 * Adds support for remember me to this authenticator.
 *
 * The presence of this badge doesn't create the remember-me cookie. The actual
 * cookie is only created if this badge is enabled. By default, this is done
 * by the {@see CheckRememberMeConditionsListener} if all conditions are met.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class RememberMeBadge implements BadgeInterface
{
    private $enabled = false;

    /**
     * Enables remember-me cookie creation.
     *
     * In most cases, {@see CheckRememberMeConditionsListener} enables this
     * automatically if always_remember_me is true or the remember_me_parameter
     * exists in the request.
     *
     * @return $this
     */
    public function enable(): self
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * Disables remember-me cookie creation.
     *
     * The default is disabled, this can be called to suppress creation
     * after it was enabled.
     *
     * @return $this
     */
    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isResolved(): bool
    {
        return true; // remember me does not need to be explicitly resolved
    }
}
