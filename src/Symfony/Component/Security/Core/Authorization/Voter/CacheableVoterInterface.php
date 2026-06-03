<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter;

/**
 * Let voters expose the attributes and types they care about.
 *
 * By returning false to either `supportsAttribute` or `supportsType`, the
 * voter will never be called for the specified attribute or subject.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface CacheableVoterInterface extends VoterInterface
{
    public function supportsAttribute(string $attribute): bool;

    /**
     * @param string $subjectType The type of the subject inferred by `get_class` or `get_debug_type`
     */
    public function supportsType(string $subjectType): bool;
}
