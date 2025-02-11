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
 * A VoteInterface implemented object can be returned by a Voter instead simple int for add some data, messages or other.
 *
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
interface VoteInterface
{
    public function getAccess(): int;

    /**
     * @return string[]
     */
    public function getMessages(): array;
}
