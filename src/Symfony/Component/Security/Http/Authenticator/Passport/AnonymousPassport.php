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

/**
 * A passport used during anonymous authentication.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 * @experimental in 5.1
 */
class AnonymousPassport implements PassportInterface
{
    use PassportTrait;
}
