<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * An implementation used when there are no credentials to be checked (e.g.
 * API token authentication).
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
class SelfValidatingPassport extends Passport
{
    /**
     * @param UserBadge        $userBadge
     * @param BadgeInterface[] $badges
     */
    public function __construct($userBadge, array $badges = [])
    {
        if ($userBadge instanceof UserInterface) {
            trigger_deprecation('symfony/security-http', '5.2', 'The 1st argument of "%s" must be an instance of "%s", support for "%s" will be removed in symfony/security-http 5.3.', __CLASS__, UserBadge::class, UserInterface::class);

            $this->user = $userBadge;
        } elseif ($userBadge instanceof UserBadge) {
            $this->addBadge($userBadge);
        } else {
            throw new \TypeError(sprintf('Argument 1 of "%s" must be an instance of "%s", "%s" given.', __METHOD__, UserBadge::class, get_debug_type($userBadge)));
        }

        foreach ($badges as $badge) {
            $this->addBadge($badge);
        }
    }
}
