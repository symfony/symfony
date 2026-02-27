<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Requirement;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Routing\Route;

class RequirementTest extends TestCase
{
    #[TestWith(['FOO'])]
    #[TestWith(['foo'])]
    #[TestWith(['1987'])]
    #[TestWith(['42-42'])]
    #[TestWith(['fo2o-bar'])]
    #[TestWith(['foo-bA198r-Ccc'])]
    #[TestWith(['fo10O-bar-CCc-fooba187rccc'])]
    public function testAsciiSlugOK(string $slug)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{slug}', [], ['slug' => Requirement::ASCII_SLUG]))->compile()->getRegex(),
            '/'.$slug,
        );
    }

    #[TestWith([''])]
    #[TestWith(['-'])]
    #[TestWith(['fôo'])]
    #[TestWith(['-FOO'])]
    #[TestWith(['foo-'])]
    #[TestWith(['-foo-'])]
    #[TestWith(['-foo-bar-'])]
    #[TestWith(['foo--bar'])]
    public function testAsciiSlugKO(string $slug)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{slug}', [], ['slug' => Requirement::ASCII_SLUG]))->compile()->getRegex(),
            '/'.$slug,
        );
    }

    #[TestWith(['foo'])]
    #[TestWith(['foo/bar/ccc'])]
    #[TestWith(['///'])]
    public function testCatchAllOK(string $path)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{path}', [], ['path' => Requirement::CATCH_ALL]))->compile()->getRegex(),
            '/'.$path,
        );
    }

    #[TestWith([''])]
    public function testCatchAllKO(string $path)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{path}', [], ['path' => Requirement::CATCH_ALL]))->compile()->getRegex(),
            '/'.$path,
        );
    }

    #[TestWith(['0000-01-01'])]
    #[TestWith(['9999-12-31'])]
    #[TestWith(['2022-04-15'])]
    #[TestWith(['2024-02-29'])]
    #[TestWith(['1243-04-31'])]
    public function testDateYmdOK(string $date)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{date}', [], ['date' => Requirement::DATE_YMD]))->compile()->getRegex(),
            '/'.$date,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['0000-01-00'])]
    #[TestWith(['9999-00-31'])]
    #[TestWith(['2022-02-30'])]
    #[TestWith(['2022-02-31'])]
    public function testDateYmdKO(string $date)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{date}', [], ['date' => Requirement::DATE_YMD]))->compile()->getRegex(),
            '/'.$date,
        );
    }

    #[TestWith(['0'])]
    #[TestWith(['012'])]
    #[TestWith(['1'])]
    #[TestWith(['42'])]
    #[TestWith(['42198'])]
    #[TestWith(['999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999'])]
    public function testDigitsOK(string $digits)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{digits}', [], ['digits' => Requirement::DIGITS]))->compile()->getRegex(),
            '/'.$digits,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['-1'])]
    #[TestWith(['3.14'])]
    public function testDigitsKO(string $digits)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{digits}', [], ['digits' => Requirement::DIGITS]))->compile()->getRegex(),
            '/'.$digits,
        );
    }

    #[TestWith(['67c8b7d295c70befc3070bf2'])]
    #[TestWith(['000000000000000000000000'])]
    public function testMongoDbIdOK(string $id)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{id}', [], ['id' => Requirement::MONGODB_ID]))->compile()->getRegex(),
            '/'.$id,
        );
    }

    #[TestWith(['67C8b7D295C70BEFC3070BF2'])]
    #[TestWith(['67c8b7d295c70befc3070bg2'])]
    #[TestWith(['67c8b7d295c70befc3070bf2a'])]
    #[TestWith(['67c8b7d295c70befc3070bf'])]
    public function testMongoDbIdKO(string $id)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{id}', [], ['id' => Requirement::MONGODB_ID]))->compile()->getRegex(),
            '/'.$id,
        );
    }

    #[TestWith(['1'])]
    #[TestWith(['42'])]
    #[TestWith(['42198'])]
    #[TestWith(['999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999'])]
    public function testPositiveIntOK(string $digits)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{digits}', [], ['digits' => Requirement::POSITIVE_INT]))->compile()->getRegex(),
            '/'.$digits,
        );
    }

    #[TestWith([''])]
    #[TestWith(['0'])]
    #[TestWith(['045'])]
    #[TestWith(['foo'])]
    #[TestWith(['-1'])]
    #[TestWith(['3.14'])]
    public function testPositiveIntKO(string $digits)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{digits}', [], ['digits' => Requirement::POSITIVE_INT]))->compile()->getRegex(),
            '/'.$digits,
        );
    }

    #[TestWith(['00000000000000000000000000'])]
    #[TestWith(['ZZZZZZZZZZZZZZZZZZZZZZZZZZ'])]
    #[TestWith(['01G0P4XH09KW3RCF7G4Q57ESN0'])]
    #[TestWith(['05CSACM1MS9RB9H5F61BYA146Q'])]
    public function testUidBase32OK(string $uid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{uid}', [], ['uid' => Requirement::UID_BASE32]))->compile()->getRegex(),
            '/'.$uid,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['01G0P4XH09KW3RCF7G4Q57ESN'])]
    #[TestWith(['01G0P4XH09KW3RCF7G4Q57ESNU'])]
    public function testUidBase32KO(string $uid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{uid}', [], ['uid' => Requirement::UID_BASE32]))->compile()->getRegex(),
            '/'.$uid,
        );
    }

    #[TestWith(['1111111111111111111111'])]
    #[TestWith(['zzzzzzzzzzzzzzzzzzzzzz'])]
    #[TestWith(['1BkPBX6T19U8TUAjBTtgwH'])]
    #[TestWith(['1fg491dt8eQpf2TU42o2bY'])]
    public function testUidBase58OK(string $uid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{uid}', [], ['uid' => Requirement::UID_BASE58]))->compile()->getRegex(),
            '/'.$uid,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['1BkPBX6T19U8TUAjBTtgw'])]
    #[TestWith(['1BkPBX6T19U8TUAjBTtgwI'])]
    public function testUidBase58KO(string $uid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{uid}', [], ['uid' => Requirement::UID_BASE58]))->compile()->getRegex(),
            '/'.$uid,
        );
    }

    #[DataProvider('provideUidRfc4122')]
    public function testUidRfc4122OK(string $uid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{uid}', [], ['uid' => Requirement::UID_RFC4122]))->compile()->getRegex(),
            '/'.$uid,
        );
    }

    #[DataProvider('provideUidRfc4122KO')]
    public function testUidRfc4122KO(string $uid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{uid}', [], ['uid' => Requirement::UID_RFC4122]))->compile()->getRegex(),
            '/'.$uid,
        );
    }

    #[DataProvider('provideUidRfc4122')]
    public function testUidRfc9562OK(string $uid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{uid}', [], ['uid' => Requirement::UID_RFC9562]))->compile()->getRegex(),
            '/'.$uid,
        );
    }

    #[DataProvider('provideUidRfc4122KO')]
    public function testUidRfc9562KO(string $uid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{uid}', [], ['uid' => Requirement::UID_RFC9562]))->compile()->getRegex(),
            '/'.$uid,
        );
    }

    public static function provideUidRfc4122(): iterable
    {
        yield ['00000000-0000-0000-0000-000000000000'];
        yield ['ffffffff-ffff-ffff-ffff-ffffffffffff'];
        yield ['01802c4e-c409-9f07-863c-f025ca7766a0'];
        yield ['056654ca-0699-4e16-9895-e60afca090d7'];
    }

    public static function provideUidRfc4122KO(): iterable
    {
        yield [''];
        yield ['foo'];
        yield ['01802c4e-c409-9f07-863c-f025ca7766a'];
        yield ['01802c4e-c409-9f07-863c-f025ca7766ag'];
        yield ['01802c4ec4099f07863cf025ca7766a0'];
    }

    #[TestWith(['00000000000000000000000000'])]
    #[TestWith(['7ZZZZZZZZZZZZZZZZZZZZZZZZZ'])]
    #[TestWith(['01G0P4ZPM69QTD4MM4ENAEA4EW'])]
    public function testUlidOK(string $ulid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{ulid}', [], ['ulid' => Requirement::ULID]))->compile()->getRegex(),
            '/'.$ulid,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['8ZZZZZZZZZZZZZZZZZZZZZZZZZ'])]
    #[TestWith(['01G0P4ZPM69QTD4MM4ENAEA4E'])]
    public function testUlidKO(string $ulid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{ulid}', [], ['ulid' => Requirement::ULID]))->compile()->getRegex(),
            '/'.$ulid,
        );
    }

    #[TestWith(['00000000-0000-1000-8000-000000000000'])]
    #[TestWith(['ffffffff-ffff-6fff-bfff-ffffffffffff'])]
    #[TestWith(['8c670a1c-bc95-11ec-8422-0242ac120002'])]
    #[TestWith(['61c86569-e477-3ed9-9e3b-1562edb03277'])]
    #[TestWith(['e55a29be-ba25-46e0-a5e5-85b78a6f9a11'])]
    #[TestWith(['bad98960-f1a1-530e-9a82-07d0b6c4e62f'])]
    #[TestWith(['1ecbc9a8-432d-6b14-af93-715adc3b830c'])]
    public function testUuidOK(string $uuid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['01802c74-d78c-b085-0cdf-7cbad87c70a3'])]
    #[TestWith(['e55a29be-ba25-46e0-a5e5-85b78a6f9a1'])]
    #[TestWith(['e55a29bh-ba25-46e0-a5e5-85b78a6f9a11'])]
    #[TestWith(['e55a29beba2546e0a5e585b78a6f9a11'])]
    #[TestWith(['21902510-bc96-21ec-8422-0242ac120002'])]
    public function testUuidKO(string $uuid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith(['00000000-0000-1000-8000-000000000000'])]
    #[TestWith(['ffffffff-ffff-1fff-bfff-ffffffffffff'])]
    #[TestWith(['21902510-bc96-11ec-8422-0242ac120002'])]
    #[TestWith(['a8ff8f60-088e-1099-a09d-53afc49918d1'])]
    #[TestWith(['b0ac612c-9117-17a1-901f-53afc49918d1'])]
    public function testUuidV1OK(string $uuid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V1]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['a3674b89-0170-3e30-8689-52939013e39c'])]
    #[TestWith(['e0040090-3cb0-4bf9-a868-407770c964f9'])]
    #[TestWith(['2e2b41d9-e08c-53d2-b435-818b9c323942'])]
    #[TestWith(['2a37b67a-5eaa-6424-b5d6-ffc9ba0f2a13'])]
    public function testUuidV1KO(string $uuid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V1]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith(['00000000-0000-3000-8000-000000000000'])]
    #[TestWith(['ffffffff-ffff-3fff-bfff-ffffffffffff'])]
    #[TestWith(['2b3f1427-33b2-30a9-8759-07355007c204'])]
    #[TestWith(['c38e7b09-07f7-3901-843d-970b0186b873'])]
    public function testUuidV3OK(string $uuid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V3]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['e24d9c0e-bc98-11ec-9924-53afc49918d1'])]
    #[TestWith(['1c240248-7d0b-41a4-9d20-61ad2915a58c'])]
    #[TestWith(['4816b668-385b-5a65-808d-bca410f45090'])]
    #[TestWith(['1d2f3104-dff6-64c6-92ff-0f74b1d0e2af'])]
    public function testUuidV3KO(string $uuid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V3]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith(['00000000-0000-4000-8000-000000000000'])]
    #[TestWith(['ffffffff-ffff-4fff-bfff-ffffffffffff'])]
    #[TestWith(['b8f15bf4-46e2-4757-bbce-11ae83f7a6ea'])]
    #[TestWith(['eaf51230-1ce2-40f1-ab18-649212b26198'])]
    public function testUuidV4OK(string $uuid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V4]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['15baaab2-f310-11d2-9ecf-53afc49918d1'])]
    #[TestWith(['acd44dc8-d2cc-326c-9e3a-80a3305a25e8'])]
    #[TestWith(['7fc2705f-a8a4-5b31-99a8-890686d64189'])]
    #[TestWith(['1ecbc991-3552-6920-998e-efad54178a98'])]
    public function testUuidV4KO(string $uuid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V4]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith(['00000000-0000-5000-8000-000000000000'])]
    #[TestWith(['ffffffff-ffff-5fff-bfff-ffffffffffff'])]
    #[TestWith(['49f4d32c-28b3-5802-8717-a2896180efbd'])]
    #[TestWith(['58b3c62e-a7df-5a82-93a6-fbe5fda681c1'])]
    public function testUuidV5OK(string $uuid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V5]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['b99ad578-fdd3-1135-9d3b-53afc49918d1'])]
    #[TestWith(['b3ee3071-7a2b-3e17-afdf-6b6aec3acf85'])]
    #[TestWith(['2ab4f5a7-6412-46c1-b3ab-1fe1ed391e27'])]
    #[TestWith(['135fdd3d-e193-653e-865d-67e88cf12e44'])]
    public function testUuidV5KO(string $uuid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V5]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith(['00000000-0000-6000-8000-000000000000'])]
    #[TestWith(['ffffffff-ffff-6fff-bfff-ffffffffffff'])]
    #[TestWith(['2c51caad-c72f-66b2-b6d7-8766d36c73df'])]
    #[TestWith(['17941ebb-48fa-6bfe-9bbd-43929f8784f5'])]
    #[TestWith(['1ecbc993-f6c2-67f2-8fbe-295ed594b344'])]
    public function testUuidV6OK(string $uuid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V6]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['821040f4-7b67-12a3-9770-53afc49918d1'])]
    #[TestWith(['802dc245-aaaa-3649-98c6-31c549b0df86'])]
    #[TestWith(['92d2e5ad-bc4e-4947-a8d9-77706172ca83'])]
    #[TestWith(['6e124559-d260-511e-afdc-e57c7025fed0'])]
    public function testUuidV6KO(string $uuid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V6]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith(['00000000-0000-7000-8000-000000000000'])]
    #[TestWith(['ffffffff-ffff-7fff-bfff-ffffffffffff'])]
    #[TestWith(['01910577-4898-7c47-966e-68d127dde2ac'])]
    public function testUuidV7OK(string $uuid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V7]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['15baaab2-f310-11d2-9ecf-53afc49918d1'])]
    #[TestWith(['acd44dc8-d2cc-326c-9e3a-80a3305a25e8'])]
    #[TestWith(['7fc2705f-a8a4-5b31-99a8-890686d64189'])]
    #[TestWith(['1ecbc991-3552-6920-998e-efad54178a98'])]
    public function testUuidV7KO(string $uuid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V7]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith(['00000000-0000-8000-8000-000000000000'])]
    #[TestWith(['ffffffff-ffff-8fff-bfff-ffffffffffff'])]
    #[TestWith(['01910577-4898-8c47-966e-68d127dde2ac'])]
    public function testUuidV8OK(string $uuid)
    {
        $this->assertMatchesRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V8]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }

    #[TestWith([''])]
    #[TestWith(['foo'])]
    #[TestWith(['15baaab2-f310-11d2-9ecf-53afc49918d1'])]
    #[TestWith(['acd44dc8-d2cc-326c-9e3a-80a3305a25e8'])]
    #[TestWith(['7fc2705f-a8a4-5b31-99a8-890686d64189'])]
    #[TestWith(['1ecbc991-3552-6920-998e-efad54178a98'])]
    public function testUuidV8KO(string $uuid)
    {
        $this->assertDoesNotMatchRegularExpression(
            (new Route('/{uuid}', [], ['uuid' => Requirement::UUID_V8]))->compile()->getRegex(),
            '/'.$uuid,
        );
    }
}
