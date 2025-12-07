<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessControl;

/**
 * @experimental
 */
interface VoterInterface
{
    public function vote(AccessRequest $accessRequest): VoterOutcome;

    public function supportsAttribute(mixed $attribute): bool;

    public function supportsSubject(mixed $subject): bool;
}
