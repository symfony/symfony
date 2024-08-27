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
 * A VoteInterface object contain information about vote, access/score, messages.
 *
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
interface VoteInterface
{
    public function __debugInfo(): array;

    public function getAccess(): int;

    public function isGranted(): bool;

    public function isAbstain(): bool;

    public function isDenied(): bool;

    public function getMessage(): string;

    public function getVoteResultMessage(): string;

    public function getContext(): array;
}
