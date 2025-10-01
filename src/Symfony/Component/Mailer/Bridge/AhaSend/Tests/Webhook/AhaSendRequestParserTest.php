<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\AhaSend\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\AhaSend\RemoteEvent\AhaSendPayloadConverter;
use Symfony\Component\Mailer\Bridge\AhaSend\Webhook\AhaSendRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class AhaSendRequestParserTest extends AbstractRequestParserTestCase
{
    private const SECRET = 'nxLe:L:fZLb7J_Wb3uFeWX/&z4Ed#9&DxPL%Ud&:jhpAW1gLaR%AEFwfKnwp60cC';

    protected function createRequestParser(): RequestParserInterface
    {
        return new AhaSendRequestParser(new AhaSendPayloadConverter());
    }

    protected function createRequest(string $payload): Request
    {
        $payloadArray = json_decode($payload, true);

        $currentDir = \dirname((new \ReflectionClass(static::class))->getFileName());
        $type = str_replace('message.', '', $payloadArray['type']);
        $headers = file_get_contents($currentDir.'/Fixtures/'.$type.'_headers.txt');
        $server = [
            'Content-Type' => 'application/json',
        ];
        foreach (explode("\n", $headers) as $row) {
            $header = explode(':', $row);
            if (2 == \count($header)) {
                $server['HTTP_'.$header[0]] = $header[1];
            }
        }
        $payload = json_encode($payloadArray, \JSON_UNESCAPED_SLASHES);

        return Request::create('/', 'POST', [], [], [], $server, $payload);
    }

    protected function getSecret(): string
    {
        return self::SECRET;
    }
}
