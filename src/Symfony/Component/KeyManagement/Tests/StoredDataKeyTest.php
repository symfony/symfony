<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\StoredDataKey;

class StoredDataKeyTest extends TestCase
{
    private const string REFERENCE = "\x11\x11\x11\x11\x11\x11\x11\x11\x11\x11\x11\x11\x11\x11\x11\x11";
    private const string SECRET = 'secret';

    public function testARowStatesTheBindingItWasMintedUnder()
    {
        $binding = StoredDataKey::bindingFor(self::REFERENCE, 'user.email', str_repeat('k', 32), self::SECRET);
        $row = new StoredDataKey(self::REFERENCE, 'user.email', new Ciphertext('wrapped', 'app'), 'aws', $binding);

        $this->assertSame($binding, $row->binding);
    }

    public function testTwoScopesAreBoundDifferentlyUnderOneKey()
    {
        $key = str_repeat('k', 32);

        $this->assertNotSame(StoredDataKey::bindingFor(self::REFERENCE, 'tenant-a.user', $key, self::SECRET), StoredDataKey::bindingFor(self::REFERENCE, 'tenant-b.user', $key, self::SECRET));
    }

    public function testTwoKeysBindOneScopeDifferently()
    {
        $this->assertNotSame(StoredDataKey::bindingFor(self::REFERENCE, 'user.email', str_repeat('a', 32), self::SECRET), StoredDataKey::bindingFor(self::REFERENCE, 'user.email', str_repeat('b', 32), self::SECRET));
    }

    public function testTwoSecretsBindOneKeyDifferently()
    {
        $key = str_repeat('k', 32);

        $this->assertNotSame(StoredDataKey::bindingFor(self::REFERENCE, 'user.email', $key, 'a'), StoredDataKey::bindingFor(self::REFERENCE, 'user.email', $key, 'b'));
    }

    public function testTwoReferencesAreBoundDifferentlyUnderOneKeyAndScope()
    {
        $key = str_repeat('k', 32);

        $this->assertNotSame(StoredDataKey::bindingFor(self::REFERENCE, 'user.email', $key, self::SECRET), StoredDataKey::bindingFor(str_repeat("\x22", 16), 'user.email', $key, self::SECRET));
    }

    public function testAReferenceDoesNotRunIntoTheScopeItIsBoundWith()
    {
        $key = str_repeat('k', 32);

        $this->assertNotSame(StoredDataKey::bindingFor('ab', 'cd', $key, self::SECRET), StoredDataKey::bindingFor('abc', 'd', $key, self::SECRET));
    }

    public function testABindingIsAlwaysTheSameWidth()
    {
        $this->assertSame(32, \strlen(StoredDataKey::bindingFor('', '', str_repeat('k', 32), self::SECRET)));
        $this->assertSame(32, \strlen(StoredDataKey::bindingFor(self::REFERENCE, str_repeat('s', 191), str_repeat('k', 32), self::SECRET)));
    }

    public function testABindingDoesNotCarryTheKeyItIsDerivedFrom()
    {
        $key = random_bytes(32);
        $binding = StoredDataKey::bindingFor(self::REFERENCE, 'user.email', $key, self::SECRET);

        $this->assertStringNotContainsString($key, $binding);
        $this->assertNotSame(hash_hmac('sha256', pack('n', 16).self::REFERENCE.'user.email', $key, true), $binding, 'the data key is not used as the HMAC key directly, a derivation stands between them');
    }

    /**
     * Every data key already stored was bound under this exact derivation, so changing it makes all
     * of them unreadable. It is pinned here rather than left to a refactoring, and a deliberate
     * change goes through the version the info string carries.
     */
    public function testTheDerivationIsNamespacedAndVersioned()
    {
        $key = str_repeat('k', 32);

        $this->assertSame(
            hash_hmac('sha256', pack('n', 16).self::REFERENCE.'user.email', hash_hkdf('sha256', $key, 32, 'symfony/key-management/data-key-binding/v1', self::SECRET), true),
            StoredDataKey::bindingFor(self::REFERENCE, 'user.email', $key, self::SECRET),
        );
    }

    public function testTwoScopesGetTheirOwnWrappingContext()
    {
        $this->assertNotSame(StoredDataKey::wrappingContextFor(self::REFERENCE, 'tenant-a.user'), StoredDataKey::wrappingContextFor(self::REFERENCE, 'tenant-b.user'));
    }

    public function testTwoReferencesGetTheirOwnWrappingContext()
    {
        $this->assertNotSame(StoredDataKey::wrappingContextFor(self::REFERENCE, 'user.email'), StoredDataKey::wrappingContextFor(str_repeat("\x22", 16), 'user.email'));
    }

    public function testAReferenceDoesNotRunIntoTheScopeItIsWrappedWith()
    {
        $this->assertNotSame(StoredDataKey::wrappingContextFor('ab', 'cd'), StoredDataKey::wrappingContextFor('abc', 'd'));
    }

    public function testTheWrappingContextIsRebuiltFromWhatTheRowStates()
    {
        $this->assertSame(StoredDataKey::wrappingContextFor(self::REFERENCE, 'user.email'), StoredDataKey::wrappingContextFor(self::REFERENCE, 'user.email'), 'the context is a pure function of what the row states, so a rewrap rebuilds it.');
    }

    public function testTheWrappingContextIsNamespacedAndVersioned()
    {
        $this->assertSame(
            'symfony/key-management/data-key-wrapping/v1'.pack('n', 16).self::REFERENCE.'user.email',
            StoredDataKey::wrappingContextFor(self::REFERENCE, 'user.email'),
        );
    }

    public function testTheWrappingContextIsNotTheBinding()
    {
        $key = str_repeat('k', 32);

        $this->assertNotSame(StoredDataKey::bindingFor(self::REFERENCE, 'user.email', $key, self::SECRET), StoredDataKey::wrappingContextFor(self::REFERENCE, 'user.email'), 'the two statements are made under their own label, so neither can be replayed as the other.');
    }
}
