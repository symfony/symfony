<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport\Credentials;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/**
 * Credentials are a special badge used to explicitly mark the
 * credential check of an authenticator.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface CredentialsInterface extends BadgeInterface
{
}
