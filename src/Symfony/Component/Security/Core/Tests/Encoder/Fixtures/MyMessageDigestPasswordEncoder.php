<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Encoder\Fixtures;

use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;

final class MyMessageDigestPasswordEncoder extends MessageDigestPasswordEncoder
{
    public function __construct()
    {
        parent::__construct('sha512', true, 1);
    }

    protected function mergePasswordAndSalt(string $password, ?string $salt): string
    {
        return json_encode(['password' => $password, 'salt' => $salt]);
    }

    protected function demergePasswordAndSalt(string $mergedPasswordSalt): array
    {
        ['password' => $password, 'salt' => $salt] = json_decode($mergedPasswordSalt, true);

        return [$password, $salt];
    }
}
