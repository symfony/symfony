<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Encoder;

use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

interface TestPasswordEncoderInterface extends PasswordEncoderInterface
{
    public function needsRehash(string $encoded): bool;
}
