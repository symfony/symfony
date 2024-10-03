<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Sendgrid\RemoteEvent\SendgridPayloadConverter;
use Symfony\Component\Mailer\Bridge\Sendgrid\Webhook\SendgridRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

/**
 * @author WoutervanderLoop.nl <info@woutervanderloop.nl>
 *
 * @requires extension openssl
 */
class SendgridSignedRequestParserTest extends AbstractRequestParserTestCase
{
    private const PRIVATE_KEY = <<<'KEY'
        -----BEGIN RSA PRIVATE KEY-----
        MIICWgIBAAKBgHH/ZmiTGDi6/1IIx4vOKedN24Zuxj9G0ioNpCbNssQlukWijQiz
        UaOZ98JgEA11jGY1gFwCKYVSH5e1ZWN+m4hdxNQoNn8QaODzyo2ocGbobzrIuMJp
        mroyl6WmNa0jW8DMoW1Mpsxo/Vw9FrkAL+eSYgR8ZFWeXbcD8yRfVm/lAgMBAAEC
        gYBDqSUtWHD96u9zz0Yw0pLIeMudBM6h6/T9hM8zQM+j4AipIAu5aEVCZzZIph+g
        /W3xlDu1YIsoWE/sCXw+C31gLgDAd/4++G+3nuQumv5TgdWyZkXrFZ+HiPk77fqh
        F6U+5vTSYS/BOueisDUY7ndgf9pFVZtj5rKHHOmL26KFgQJBAK6npY3H1UyYHi/t
        vaxH/5KVqBDWuUE1+MjyVF0KbjyZOzMka7/4DenbBsZRDCqNrP8psuCwOFPf+vwN
        uVmE7vECQQCnF4F/INbeZkL3EQTMhCF3kIuY9jtB/ah+FQ/zom0gcw4zNAzKVeFm
        SmCTAeZbqq+fTFgwueIE4mPv4hiT0Hg1AkBIqoGr6p+pPYUZxd1rh40i7Nc/Ikdz
        hUQcPw6woz1YQxypW5blCQyo5rL74g6gyc9XXn8JEuhspTzkj8U1JKTRAkASdDAj
        IDda3KRssP58r+MaV2ZzgE5PHXqsYhse50NyIALjeM4o0o9QQsqjscQFP7ahu0bK
        Kt1heLdc2PWp7Y45AkAdAVZd//vS9FLU397DZAf7h+5qhUmPkm8vxnehCH+olQXq
        SPExlMI7PVpISz7jk9hRF31NStTZok//CUcq+yxJ
        -----END RSA PRIVATE KEY-----
        KEY;
    private const SECRET = 'MIGeMA0GCSqGSIb3DQEBAQUAA4GMADCBiAKBgHH/ZmiTGDi6/1IIx4vOKedN24Zuxj9G0ioNpCbNssQlukWijQizUaOZ98JgEA11jGY1gFwCKYVSH5e1ZWN+m4hdxNQoNn8QaODzyo2ocGbobzrIuMJpmroyl6WmNa0jW8DMoW1Mpsxo/Vw9FrkAL+eSYgR8ZFWeXbcD8yRfVm/lAgMBAAE=';

    protected function createRequestParser(): RequestParserInterface
    {
        return new SendgridRequestParser(new SendgridPayloadConverter(), true);
    }

    /**
     * @see https://github.com/sendgrid/sendgrid-php/blob/9335dca98bc64456a72db73469d1dd67db72f6ea/test/unit/EventWebhookTest.php#L20
     */
    protected function createRequest(string $payload): Request
    {
        $payload = str_replace("\n", "\r\n", $payload);

        openssl_sign('1600112502'.$payload, $signature, self::PRIVATE_KEY, \OPENSSL_ALGO_SHA256);

        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_X-Twilio-Email-Event-Webhook-Signature' => base64_encode($signature),
            'HTTP_X-Twilio-Email-Event-Webhook-Timestamp' => '1600112502',
        ], $payload);
    }

    protected function getSecret(): string
    {
        return self::SECRET;
    }
}
