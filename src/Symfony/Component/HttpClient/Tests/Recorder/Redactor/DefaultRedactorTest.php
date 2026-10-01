<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Recorder\Redactor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Recorder\Redactor\DefaultRedactor;

class DefaultRedactorTest extends TestCase
{
    public function testRedactUrlMasksDenyListedQueryParamsOnly()
    {
        $redactor = new DefaultRedactor();

        $this->assertSame(
            'https://example.com/path?foo=bar&token=%5BREDACTED%5D',
            $redactor->redactUrl('https://example.com/path?foo=bar&token=secret')
        );
    }

    public function testRedactUrlLeavesUrlWithoutQueryUntouched()
    {
        $redactor = new DefaultRedactor();

        $this->assertSame('https://example.com/path', $redactor->redactUrl('https://example.com/path'));
    }

    public function testRedactUrlIsDeterministic()
    {
        $redactor = new DefaultRedactor();
        $url = 'https://example.com/path?token=secret&foo=bar';

        $this->assertSame($redactor->redactUrl($url), $redactor->redactUrl($url));
    }

    public function testRedactHeadersMasksDenyListedHeadersOnly()
    {
        $redactor = new DefaultRedactor();

        $redacted = $redactor->redactHeaders([
            'authorization' => ['Bearer secret'],
            'x-custom' => ['keep-me'],
        ]);

        $this->assertSame(['[REDACTED]'], $redacted['authorization']);
        $this->assertSame(['keep-me'], $redacted['x-custom']);
    }

    public function testRedactBodyMasksDenyListedJsonFieldsOnly()
    {
        $redactor = new DefaultRedactor();

        $redacted = $redactor->redactBody(json_encode(['username' => 'bob', 'password' => 'hunter2']));
        $decoded = json_decode($redacted, true);

        $this->assertSame('bob', $decoded['username']);
        $this->assertSame('[REDACTED]', $decoded['password']);
    }

    public function testRedactBodyLeavesNonJsonBodyUntouched()
    {
        $redactor = new DefaultRedactor();

        $this->assertSame('not-json=1', $redactor->redactBody('not-json=1'));
    }

    public function testRedactBodyLeavesNullAndEmptyUntouched()
    {
        $redactor = new DefaultRedactor();

        $this->assertNull($redactor->redactBody(null));
        $this->assertSame('', $redactor->redactBody(''));
    }

    public function testRedactBodyMasksOAuthTokenResponse()
    {
        $redactor = new DefaultRedactor();

        $redacted = $redactor->redactBody('{"access_token":"at","token_type":"Bearer","expires_in":3600,"refresh_token":"rt","id_token":"it","scope":"openid"}');
        $decoded = json_decode($redacted, true);

        $this->assertSame('[REDACTED]', $decoded['access_token']);
        $this->assertSame('Bearer', $decoded['token_type']);
        $this->assertSame(3600, $decoded['expires_in']);
        $this->assertSame('[REDACTED]', $decoded['refresh_token']);
        $this->assertSame('[REDACTED]', $decoded['id_token']);
        $this->assertSame('openid', $decoded['scope']);
    }

    public function testRedactBodyLeavesJsonErrorCodeUntouched()
    {
        $redactor = new DefaultRedactor();

        $redacted = $redactor->redactBody('{"code":"E_NOT_FOUND","message":"nope"}');
        $decoded = json_decode($redacted, true);

        $this->assertSame('E_NOT_FOUND', $decoded['code']);
        $this->assertSame('nope', $decoded['message']);
    }

    public function testRedactBodyMasksFormEncodedTokenRequest()
    {
        $redactor = new DefaultRedactor();

        $redacted = $redactor->redactBody('grant_type=authorization_code&code=abc&client_secret=s3cr3t&redirect_uri=https%3A%2F%2Fapp%2Fcb&code_verifier=v');
        parse_str($redacted, $result);

        $this->assertSame('authorization_code', $result['grant_type']);
        $this->assertSame('[REDACTED]', $result['code']);
        $this->assertSame('[REDACTED]', $result['client_secret']);
        $this->assertSame('https://app/cb', $result['redirect_uri']);
        $this->assertSame('[REDACTED]', $result['code_verifier']);
    }

    public function testRedactBodyMasksSamlFormPost()
    {
        $redactor = new DefaultRedactor();

        $redacted = $redactor->redactBody('SAMLResponse=PHNhbWxwOlJlc3BvbnNlPg&RelayState=abc');
        parse_str($redacted, $result);

        $this->assertSame('[REDACTED]', $result['SAMLResponse']);
        $this->assertSame('abc', $result['RelayState']);
    }

    public function testRedactUrlMasksFragment()
    {
        $redactor = new DefaultRedactor();

        $redacted = $redactor->redactUrl('https://app.example/cb#access_token=tok&state=xyz&token_type=bearer');

        $this->assertStringContainsString('access_token=%5BREDACTED%5D', $redacted);
        $this->assertStringContainsString('state=xyz', $redacted);
        $this->assertStringContainsString('token_type=bearer', $redacted);
    }

    public function testRedactHeadersRewritesLocation()
    {
        $redactor = new DefaultRedactor();

        $redacted = $redactor->redactHeaders([
            'location' => ['https://app.example/cb?code=abc&state=xyz'],
            'dpop' => ['proof'],
        ]);

        $this->assertStringContainsString('code=%5BREDACTED%5D', $redacted['location'][0]);
        $this->assertStringContainsString('state=xyz', $redacted['location'][0]);
        $this->assertSame(['[REDACTED]'], $redacted['dpop']);
    }

    public function testConstructorArgumentsAreAddedToTheDefaults()
    {
        $redactor = new DefaultRedactor(['X-Custom-Secret'], ['sig'], ['pin']);

        // Test headers: authorization (default) and x-custom-secret (custom) should be masked
        $redacted = $redactor->redactHeaders([
            'authorization' => ['Bearer secret'],
            'X-Custom-Secret' => ['custom-value'],
        ]);
        $this->assertSame(['[REDACTED]'], $redacted['authorization']);
        $this->assertSame(['[REDACTED]'], $redacted['X-Custom-Secret']);

        // Test query params: token (default) and sig (custom) should be masked
        $this->assertSame(
            'https://example.com/path?foo=bar&token=%5BREDACTED%5D&sig=%5BREDACTED%5D',
            $redactor->redactUrl('https://example.com/path?foo=bar&token=secret&sig=value')
        );

        // Test body fields: password (default) and pin (custom) should be masked
        $redactedBody = $redactor->redactBody(json_encode(['username' => 'bob', 'password' => 'hunter2', 'pin' => '1234']));
        $decoded = json_decode($redactedBody, true);
        $this->assertSame('bob', $decoded['username']);
        $this->assertSame('[REDACTED]', $decoded['password']);
        $this->assertSame('[REDACTED]', $decoded['pin']);
    }

    public function testRedactBodyMasksNestedJsonObjectsUnderDenyListedKeys()
    {
        $redactor = new DefaultRedactor();

        $redacted = $redactor->redactBody('{"password":{"value":"hunter2"},"token":{"access_token":"x","expires_in":3600},"username":"bob"}');
        $decoded = json_decode($redacted, true);

        $this->assertSame('[REDACTED]', $decoded['password']);
        $this->assertSame('[REDACTED]', $decoded['token']);
        $this->assertSame('bob', $decoded['username']);
    }

    public function testRedactBodyMasksBracketStyleFormFields()
    {
        $redactor = new DefaultRedactor();

        $redacted = $redactor->redactBody('user[password]=secret&user[name]=bob&foo=bar');
        parse_str($redacted, $result);

        $this->assertSame('[REDACTED]', $result['user']['password']);
        $this->assertSame('bob', $result['user']['name']);
        $this->assertSame('bar', $result['foo']);
    }

    public function testRedactUrlMasksBracketStyleQueryParams()
    {
        $redactor = new DefaultRedactor();

        $redacted = $redactor->redactUrl('https://example.com/x?session[api_key]=xyz&a=1');

        $this->assertStringContainsString('session%5Bapi_key%5D=%5BREDACTED%5D', $redacted);
        $this->assertStringContainsString('a=1', $redacted);
        $this->assertStringNotContainsString('xyz', $redacted);
    }

    public function testRedactUrlMasksUserInfo()
    {
        $redactor = new DefaultRedactor();

        $this->assertSame(
            'https://%5BREDACTED%5D@example.com/path',
            $redactor->redactUrl('https://user:pw@example.com/path')
        );

        $redacted = $redactor->redactUrl('https://user:pw@example.com/path?token=t&a=1');
        $this->assertStringContainsString('%5BREDACTED%5D@example.com/path?', $redacted);
        $this->assertStringContainsString('token=%5BREDACTED%5D', $redacted);
        $this->assertStringContainsString('a=1', $redacted);
        $this->assertStringNotContainsString('user:pw', $redacted);
    }

    public function testRedactBodyLeavesUntouchedJsonByteIdentical()
    {
        $redactor = new DefaultRedactor();
        $body = '{"url":"https://x/y","name":"caf\u00e9"}';

        $this->assertSame($body, $redactor->redactBody($body));
    }

    public function testRedactBodyKeepsTheRestOfTheJsonByteIdenticalWhenMasking()
    {
        $redactor = new DefaultRedactor();

        $this->assertSame(
            '{"meta":{},"password":"[REDACTED]","n":1.0,"big":12345678901234567890,"url":"https:\\/\\/x\\/y","name":"caf\\u00e9"}',
            $redactor->redactBody('{"meta":{},"password":"x","n":1.0,"big":12345678901234567890,"url":"https:\\/\\/x\\/y","name":"caf\\u00e9"}')
        );
    }

    public function testRedactBodyKeepsTheJsonFormattingWhenMasking()
    {
        $redactor = new DefaultRedactor();

        $body = <<<'JSON'
            {
                "user": {
                    "name": "bob",
                    "password" : "hunter2" ,
                    "roles": ["a", "b"]
                },
                "tokens": [{"access_token": {"nested": true}}, {"scope": "openid"}]
            }
            JSON;

        $this->assertSame(<<<'JSON'
            {
                "user": {
                    "name": "bob",
                    "password" : "[REDACTED]" ,
                    "roles": ["a", "b"]
                },
                "tokens": [{"access_token": "[REDACTED]"}, {"scope": "openid"}]
            }
            JSON, $redactor->redactBody($body));
    }

    public function testRedactBodyDoesNotMatchKeysInsideStringValues()
    {
        $redactor = new DefaultRedactor();
        $body = '{"note":"a \\"password\\": \\"x\\" inside","password":"secret"}';

        $this->assertSame('{"note":"a \\"password\\": \\"x\\" inside","password":"[REDACTED]"}', $redactor->redactBody($body));
    }

    public function testRedactBodyMatchesEscapedKeys()
    {
        $redactor = new DefaultRedactor();

        $this->assertSame('{"pass\\u0077ord":"[REDACTED]"}', $redactor->redactBody('{"pass\\u0077ord":"secret"}'));
    }

    public function testRedactBodyHandlesLargeBodies()
    {
        $redactor = new DefaultRedactor();
        $body = json_encode(['data' => array_fill(0, 20000, ['id' => 1, 'tags' => ['a', 'b'], 'meta' => ['deep' => [1, 2]]]), 'access_token' => 'secret']);

        $this->assertSame(str_replace('"secret"', '"[REDACTED]"', $body), $redactor->redactBody($body));
    }

    public function testExceptRemovesNamesFromAllLists()
    {
        $redactor = new DefaultRedactor([], [], ['pin'], ['Code', 'PIN', 'Authorization']);

        $this->assertSame('https://example.com/cb?code=abc&state=xyz', $redactor->redactUrl('https://example.com/cb?code=abc&state=xyz'));
        $this->assertSame('code=abc&state=xyz', $redactor->redactBody('code=abc&state=xyz'));
        $this->assertSame('{"pin":"1234"}', $redactor->redactBody('{"pin":"1234"}'));
        $this->assertSame(['authorization' => ['Bearer x']], $redactor->redactHeaders(['authorization' => ['Bearer x']]));
        $this->assertSame('{"password":"[REDACTED]"}', $redactor->redactBody('{"password":"secret"}'));
    }

    public function testRedactBodyMasksCodeInFormPostResponse()
    {
        $redactor = new DefaultRedactor();

        parse_str($redactor->redactBody('code=abc&state=xyz'), $result);

        $this->assertSame('[REDACTED]', $result['code']);
        $this->assertSame('xyz', $result['state']);
    }

    public function testRedactBodyLeavesUntouchedFormBodyByteIdentical()
    {
        $redactor = new DefaultRedactor();
        $body = 'a.b=1&a.b=2&foo=bar';

        $this->assertSame($body, $redactor->redactBody($body));
    }
}
