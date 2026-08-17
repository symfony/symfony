<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Fixtures\Bridge;

use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;

/**
 * A backend living under a `Bridge` namespace, as every shipped provider does.
 *
 * It exists to exercise how {@see \Symfony\Component\KeyManagement\DataCollector\KeyManagementDataCollector}
 * tells apart what stayed in the process from what left it.
 */
final class RemoteKms implements EncrypterInterface, DecrypterInterface
{
    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        return new Ciphertext('remote/'.$plaintext, $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        return substr($ciphertext->blob, \strlen('remote/'));
    }
}
