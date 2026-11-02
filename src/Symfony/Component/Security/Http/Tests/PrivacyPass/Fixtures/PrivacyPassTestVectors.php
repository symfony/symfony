<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\PrivacyPass\Fixtures;

/**
 * The test vectors of RFC 9577 and RFC 9578, for token type 0x0002, as hexadecimal strings.
 */
final class PrivacyPassTestVectors
{
    /**
     * The issuer public key of the test vectors of RFC 9578, Appendix A.2.
     */
    public const TOKEN_KEY = '30820152303d06092a864886f70d01010a3030a00d300b0609608648016503040202a11a301806092a864886f70d010108300b0609608648016503040202a2030201300382010f003082010a0282010100cb1aed6b6a95f5b1ce013a4cfcab25b94b2e64a23034e4250a7eab43c0df3a8c12993af12b111908d4b471bec31d4b6c9ad9cdda90612a2ee903523e6de5a224d6b02f09e5c374d0cfe01d8f529c500a78a2f67908fa682b5a2b430c81eaf1af72d7b5e794fc98a3139276879757ce453b526ef9bf6ceb99979b8423b90f4461a22af37aab0cf5733f7597abe44d31c732db68a181c6cbbe607d8c0e52e0655fd9996dc584eca0be87afbcd78a337d17b1dba9e828bbd81e291317144e7ff89f55619709b096cbb9ea474cead264c2073fe49740c01f00e109106066983d21e5f83f086e2e823c879cd43cef700d2a352a9babd612d03cad02db134b7e225a5f0203010001';

    /**
     * The identifier of that key, as the test vectors of RFC 9577, Appendix A.1, give it.
     */
    public const TOKEN_KEY_ID = 'ca572f8982a9ca248a3056186322d93ca147266121ddeb5632c07f1f71cd2708';

    /**
     * The same modulus and exponent, carrying the id-rsaEncryption object identifier: a key whose
     * signatures OpenSSL verifies against PKCS #1 v1.5 instead of RSASSA-PSS.
     */
    public const TOKEN_KEY_RSA_ENCRYPTION = '30820122300d06092a864886f70d01010105000382010f003082010a0282010100cb1aed6b6a95f5b1ce013a4cfcab25b94b2e64a23034e4250a7eab43c0df3a8c12993af12b111908d4b471bec31d4b6c9ad9cdda90612a2ee903523e6de5a224d6b02f09e5c374d0cfe01d8f529c500a78a2f67908fa682b5a2b430c81eaf1af72d7b5e794fc98a3139276879757ce453b526ef9bf6ceb99979b8423b90f4461a22af37aab0cf5733f7597abe44d31c732db68a181c6cbbe607d8c0e52e0655fd9996dc584eca0be87afbcd78a337d17b1dba9e828bbd81e291317144e7ff89f55619709b096cbb9ea474cead264c2073fe49740c01f00e109106066983d21e5f83f086e2e823c879cd43cef700d2a352a9babd612d03cad02db134b7e225a5f0203010001';

    /**
     * Another 2048-bit key with the same RSASSA-PSS parameters, which signed none of the tokens.
     */
    public const OTHER_TOKEN_KEY = '30820156304106092a864886f70d01010a3034a00f300d06096086480165030402020500a11c301a06092a864886f70d010108300d06096086480165030402020500a2030201300382010f003082010a0282010100ba7169a53c3fc7c6c96165a430ce2d3a07b90c6d6dab34d0754f540ac66d5e7eff3284eb3f8c361c4f1d224d61ca0092b1ad3f60d5e2e0471d32315aef1e5b89451fa7e0be37a24bec86b5a7723e6b7590812a0f6a2ccd793fb90adbae536660c276457695b63a7a1a9d87c1a73186af3111b099a56ceb40b6307b451003a9c0a42d00375cd7ec61927f36d72aef04bd454f5fa39a73fb6ac9828c3d56e86adfda4b2419fb3878f282a15d3d2d6488598cdadf6cc9407f5f0c4303870b498deb00690978a2e692ade9ba33239e4c5d8d732235a85cb973d7c60fd5c685ebc049bd496a84c8d3276e3a75d63f6b2b905f1e94d693f7e6f59feec9b8140312aa390203010001';

    /**
     * A 3072-bit key with the same RSASSA-PSS parameters, as OpenSSL encodes them: a key too large
     * for the 256-byte authenticator of a token of type 0x0002.
     */
    public const TOKEN_KEY_3072 = '308201d6304106092a864886f70d01010a3034a00f300d06096086480165030402020500a11c301a06092a864886f70d010108300d06096086480165030402020500a2030201300382018f003082018a0282018100aab910eef053f98fe2636618e701d1d2fa3fb3847a36270bba750e7a85552fcfc002520a2f9a9c232df6e8e041ab560c74c6db3a586c7aa2f14e5264484ef63830ca88739d91173ab70852cede5a3d211e41a8c911f7bd0d5953c5b6f90751a53cb36109f5b03d582bd35ddb2b6aa92de8f389c710aeefde50ff9ecdfb9a11919703577e0fcf0d3850fb4bda3424769863bd3bb24b079c0b2082b8c9a29e1310f706ad023acff2189d8b6ad440625c26baf2b5129c35f852853490273b5f0ddc21cf990e385e4789f44ed0423438320d52fe78fd074b65a357d3d9e7f1977d46484703400384815fb7d45f0819387bb899dc566deddece439bf7065edd91c3f0f39a002f1a361dd1c94e05e96f58cc2abd07eba317e3c34f269b3aad9d3852d3d9851b8d0010dbba1049f65b18db242ac7618e4282e96860f8de303aa13c26f0a1bb27a4e5db869a20266ed092fb2b412ea1b755510ff613a73ccc23e16f915983e8c3b595cca1573eb4f64f23fd27a0e46eb9c65e73821712fdb81999b4369f0203010001';

    /**
     * The five test vectors of RFC 9578, Appendix A.2: a token, and the challenge it answers.
     */
    public const TOKENS = [
        [
            'issuer_name' => 'issuer.example',
            'redemption_context' => '8e7acc900e393381e8810b7c9e4a68b5163f1f880ab6688a6ffe780923609e88',
            'origin_info' => ['origin.example'],
            'nonce' => 'aa72019d1f951df197021ce63876fe8b0a02dc1c31a12b0a2dd1508d07827f05',
            'challenge' => '0002000e6973737565722e6578616d706c65208e7acc900e393381e8810b7c9e4a68b5163f1f880ab6688a6ffe780923609e88000e6f726967696e2e6578616d706c65',
            'token' => '0002aa72019d1f951df197021ce63876fe8b0a02dc1c31a12b0a2dd1508d07827f055969f643b4cfda5196d4aa86aeb5368834f4f06de46950ed435b3b81bd036d44ca572f8982a9ca248a3056186322d93ca147266121ddeb5632c07f1f71cd2708bc6a21b533d07294b5e900faf5537dd3eb33cee4e08c9670d1e5358fd184b0e00c637174f5206b14c7bb0e724ebf6b56271e5aa2ed94c051c4a433d302b23bc52460810d489fb050f9de5c868c6c1b06e3849fd087629f704cc724bc0d0984d5c339686fcdd75f9a9cdd25f37f855f6f4c584d84f716864f546b696d620c5bd41a811498de84ff9740ba3003ba2422d26b91eb745c084758974642a42078201543246ddb58030ea8e722376aa82484dca9610a8fb7e018e396165462e17a03e40ea7e128c090a911ecc708066cb201833010c1ebd4e910fc8e27a1be467f78671836a508257123a45e4e0ae2180a434bd1037713466347a8ebe46439d3da1970',
        ],
        [
            'issuer_name' => 'issuer.example',
            'redemption_context' => '',
            'origin_info' => ['origin.example'],
            'nonce' => '98c1345ff38a554b429b428b0f206cfe4f3892f8041995f2c24873d90e84488d',
            'challenge' => '0002000e6973737565722e6578616d706c6500000e6f726967696e2e6578616d706c65',
            'token' => '000298c1345ff38a554b429b428b0f206cfe4f3892f8041995f2c24873d90e84488d11e15c91a7c2ad02abd66645802373db1d823bea80f08d452541fb2b62b5898bca572f8982a9ca248a3056186322d93ca147266121ddeb5632c07f1f71cd27083350a206c5e9b7c0898f97611ce0bb8d74d310bb194ab67e094e32ff6da90886924b1b9e7b569402c1101d896d2fc3a7371ef77f02310db1dc9f81c8535828c2d0e9d9051720d182cd54e1c2c3bf417da2fc7aa72bb70ccc834ef274a2e809c9821b3d395d6535423f7428b3f29175d6eb840b4b7685336e57e2b6afeaabc0c17ea4f557e8a9cc2f624e245c6ccd7cbdd6c32c97c5c6974e802f688e2d25f0aba4215f609f692244517d5d3407e0172273982c001c158f5fcbe1b5d2447c26a87e89f5a9e72b498b0c59ce749823d2cf253d3cf6cd4e64fa0e434d95e488789247a9ceed756ff4ff33a8d2402c0db381236d331092838b608a42002552092897',
        ],
        [
            'issuer_name' => 'issuer.example',
            'redemption_context' => '',
            'origin_info' => ['foo.example', 'bar.example'],
            'nonce' => '9e7a22bdc5d715682434cebc07eb5fa53f622f776a17a6d91757af1592df0e71',
            'challenge' => '0002000e6973737565722e6578616d706c65000017666f6f2e6578616d706c652c6261722e6578616d706c65',
            'token' => '00029e7a22bdc5d715682434cebc07eb5fa53f622f776a17a6d91757af1592df0e710042eee45ac4dd5acb8f6e65c4d8dd47504f73f7463507ef96a4d7227d2774f3ca572f8982a9ca248a3056186322d93ca147266121ddeb5632c07f1f71cd270815b010bbc0d5f55e9c856d2e9ffaefba007d33c2d5452fbeb0b15919b973e0dc9180aaeb18242043758d9fb0ac9ac5e04da9ff74ec93644ae6cdb7068ea76ce2295b9b95e383ed3a9856e9f618dafdf4cec5d2b53ea4297c2f3990babca71e3ccd6c07a437daae7ed27b6b81178fb7ce5fa5dd63781cc64ac1e410f441c034b0a5cc873a2ce875e8b38c92bab563635c4f8f4fa35d1f582ef19edf7da75aa11a503a82e32a12bd4da41e0ca7ec7f451caf586f5b910003fcbbb9ff5ffa2408c28d6807737d03da651ea9bfafcc2747a6830e19a1d160fcd5c25d2f79dad86a8b3de8e926e08ca1addced72977f7b56398ef59c26e725df0a976a08f2a936ca42',
        ],
        [
            'issuer_name' => 'issuer.example',
            'redemption_context' => '',
            'origin_info' => [],
            'nonce' => '494dae41fc7e300c2d09990afcd5d5e1fc95305337dc12f78942c45340bfe8e6',
            'challenge' => '0002000e6973737565722e6578616d706c65000000',
            'token' => '0002494dae41fc7e300c2d09990afcd5d5e1fc95305337dc12f78942c45340bfe8e6b741ec1b6fd05f1e95f8982906aec1612896d9ca97d53eef94ad3c9fe023f7a4ca572f8982a9ca248a3056186322d93ca147266121ddeb5632c07f1f71cd2708a55c83dc04292b5d92add1a87b37e54f22f61c58840586f390c50b231824423378ddcf50e69dc817d45bfad06c7f2a0ac35d2acd7f26b0bc9954c192b0a0ef28a2a5650e390098dd3cb1166a7cb1716d3dd2d19dc5ca3b1ea6206359de0002d82bc4fa7e69fb07214b06addcbd2203d1e17f57fc580bcc5a13e0ac15cf942182cc2b5d6eaa737a712704114e357e2ec2f10047463ded02a1a0766dc346dd7212b9711e03ac95eb258ac1164104dc9a0d3e738ae742ab5ed8c5139fc07145a788b9f891741ee68f0a66782b7b84a9bb4cb4b3d1b26b67106f397b35b641d882d7b0185168946de898ef72349a44a47dbdd6d46e9ba9ba543d5701b65c63d645c2',
        ],
        [
            'issuer_name' => 'issuer.example',
            'redemption_context' => '8e7acc900e393381e8810b7c9e4a68b5163f1f880ab6688a6ffe780923609e88',
            'origin_info' => [],
            'nonce' => 'a1aa8b371c37c9a8ddbd7342ab4f9dd5227d5b1600dca6517b60f63143cd43a3',
            'challenge' => '0002000e6973737565722e6578616d706c65208e7acc900e393381e8810b7c9e4a68b5163f1f880ab6688a6ffe780923609e880000',
            'token' => '0002a1aa8b371c37c9a8ddbd7342ab4f9dd5227d5b1600dca6517b60f63143cd43a3bb8a8cf1c59e7a251358ed76fe0ccff61044bc79dd261f16020324d22f2d434cca572f8982a9ca248a3056186322d93ca147266121ddeb5632c07f1f71cd27082899f4bc385b4147a650a3e7efc7d23a743cb944bb53e2ed8b88ee03a9c0b1cf1f73fd7f15c1bd1f850a5a96a34d4ee9f295543f30ac920825e9a0ce26e924f2c145583019dd14408c565b660e8b702fbea559908b1a8c8f9d21ef22f7cc6a4641c57c146e6c5497d2890ca230a6749f5f83a8fdd79eba0722f10dff9e81a2fb2d05fa4d989acc2e93f595ae69c98c3daa3b169efcdd6e59596b2f049f16ba49528761f661032da65a3ee0fe8a22409766e90daf8c60323c16522818c49273c795f26bbab306dc63cfc16fe1702af2464028cf819cc647d6f9b8a8f54d7d6585a268fdb8c75f76618c64aba266836a3b2db7cdd739815a021d7ff2b36ef91f23',
        ],
    ];

    /**
     * A token of type 0x0001, from the test vectors of RFC 9578, Appendix A.1.
     */
    public const VOPRF_TOKEN = '00016aa422c41b59d3e44a136dd439df2454e3587ee5f3697798cdc05fafe73073b8501370b494089dc462802af545e63809581ee6ef57890a12105c28368169514bf260d0792bf7f46c9866a6d37c3032d8714415f87f5f6903d7fb071e253be2f4e0a835d76528b8444f73789ee7dc90715b01c17902fd87375c00a7a9d3d92540437f470773be20f71e721da3af40edeb';

    /**
     * The five test vectors of RFC 9577, Appendix A.1: a challenge, and the authenticator input of
     * a token answering it.
     */
    public const AUTHENTICATOR_INPUTS = [
        [
            'issuer_name' => 'issuer.example',
            'redemption_context' => '476ac2c935f458e9b2d7af32dacfbd22dd6023ef5887a789f1abe004e79bb5bb',
            'origin_info' => ['origin.example'],
            'authenticator_input' => '0002e01978182c469e5e026d66558ee186568614f235e41ef7e2378e6f202688abab8e1d5518ec82964255526efd8f9db88205a8ddd3ffb1db298fcc3ad36c42388fca572f8982a9ca248a3056186322d93ca147266121ddeb5632c07f1f71cd2708',
        ],
        [
            'issuer_name' => 'issuer.example',
            'redemption_context' => '',
            'origin_info' => ['origin.example'],
            'authenticator_input' => '0002e01978182c469e5e026d66558ee186568614f235e41ef7e2378e6f202688abab11e15c91a7c2ad02abd66645802373db1d823bea80f08d452541fb2b62b5898bca572f8982a9ca248a3056186322d93ca147266121ddeb5632c07f1f71cd2708',
        ],
        [
            'issuer_name' => 'issuer.example',
            'redemption_context' => '',
            'origin_info' => [],
            'authenticator_input' => '0002e01978182c469e5e026d66558ee186568614f235e41ef7e2378e6f202688ababb741ec1b6fd05f1e95f8982906aec1612896d9ca97d53eef94ad3c9fe023f7a4ca572f8982a9ca248a3056186322d93ca147266121ddeb5632c07f1f71cd2708',
        ],
        [
            'issuer_name' => 'issuer.example',
            'redemption_context' => '476ac2c935f458e9b2d7af32dacfbd22dd6023ef5887a789f1abe004e79bb5bb',
            'origin_info' => [],
            'authenticator_input' => '0002e01978182c469e5e026d66558ee186568614f235e41ef7e2378e6f202688ababb85fb5bc06edeb0e8e8bdb5b3bea8c4fa40837c82e8bcaf5882c81e14817ea18ca572f8982a9ca248a3056186322d93ca147266121ddeb5632c07f1f71cd2708',
        ],
        [
            'issuer_name' => 'issuer.example',
            'redemption_context' => '476ac2c935f458e9b2d7af32dacfbd22dd6023ef5887a789f1abe004e79bb5bb',
            'origin_info' => ['foo.example', 'bar.example'],
            'authenticator_input' => '0002e01978182c469e5e026d66558ee186568614f235e41ef7e2378e6f202688ababa2a775866b6ae0f98944910c8f48728d8a2735b9157762ddbf803f70e2e8ba3eca572f8982a9ca248a3056186322d93ca147266121ddeb5632c07f1f71cd2708',
        ],
    ];

    /**
     * The first challenge of the "WWW-Authenticate" test vector of RFC 9577, Appendix A.2, and the
     * value of its "challenge" and "token-key" parameters.
     */
    public const HEADER_CHALLENGE = '0002000e6973737565722e6578616d706c65208a3e83a33d98005d2f30bef419fa6bf4cd5c6005e36b1285bbb4ccd40fa4b383000e6f726967696e2e6578616d706c65';
    public const HEADER_CHALLENGE_BASE64URL = 'AAIADmlzc3Vlci5leGFtcGxlIIo-g6M9mABdLzC-9Bn6a_TNXGAF42sShbu0zNQPpLODAA5vcmlnaW4uZXhhbXBsZQ==';
    public const HEADER_TOKEN_KEY_BASE64URL = 'MIIBUjA9BgkqhkiG9w0BAQowMKANMAsGCWCGSAFlAwQCAqEaMBgGCSqGSIb3DQEBCDALBglghkgBZQMEAgKiAwIBMAOCAQ8AMIIBCgKCAQEAyxrta2qV9bHOATpM_KsluUsuZKIwNOQlCn6rQ8DfOowSmTrxKxEZCNS0cb7DHUtsmtnN2pBhKi7pA1I-beWiJNawLwnlw3TQz-Adj1KcUAp4ovZ5CPpoK1orQwyB6vGvcte155T8mKMTknaHl1fORTtSbvm_bOuZl5uEI7kPRGGiKvN6qwz1cz91l6vkTTHHMttooYHGy75gfYwOUuBlX9mZbcWE7KC-h6-814ozfRex26noKLvYHikTFxROf_ifVWGXCbCWy7nqR0zq0mTCBz_kl0DAHwDhCRBgZpg9IeX4PwhuLoI8h5zUPO9wDSo1Kpur1hLQPK0C2xNLfiJaXwIDAQAB';
}
