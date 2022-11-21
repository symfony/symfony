<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport\Smtp\Auth;

use Exception;
use LogicException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * Handles NTLM authentication.
 *
 * @author De Bleye Peter<peter.debleye@gmail.com> | Ward Peeters <ward@coding-tech.com>
 *
 * @see https://github.com/swiftmailer/swiftmailer/blob/master/lib/classes/Swift/Transport/Esmtp/Auth/NTLMAuthenticator.php
 */
class NtlmAuthenticator implements AuthenticatorInterface
{
    public const NTLMSIG = "NTLMSSP\x00";
    public const DESCONST = 'KGS!@#$%';

    public function getAuthKeyword(): string
    {
        return 'NTLM';
    }

    /**
     * @throws LogicException|Exception
     */
    public function authenticate(EsmtpTransport $client): void
    {
        $username = $client->getUsername();
        $password = $client->getPassword();
        if (!\function_exists('openssl_encrypt')) {
            throw new LogicException('The OpenSSL extension must be enabled to use the NTLM authenticator.');
        }

        if (!\function_exists('bcmul')) {
            throw new LogicException('The BCMath functions must be enabled to use the NTLM authenticator.');
        }

        try {
            // execute AUTH command and filter out the code at the beginning
            // AUTH NTLM xxxx
            $response = base64_decode(substr(trim($this->sendMessage1($client) ?? ''), 4));

            // Message 3 response
            $this->sendMessage3($response, $client);
        } catch (\Symfony\Component\Mailer\Exception\TransportException $e) {
            $client->executeCommand("RSET\r\n", [250]);

            throw $e;
        }
    }

    protected function si2bin(string $si, int $bits = 32): ?string
    {
        $bin = null;
        if ($si >= -2 ** ($bits - 1) && ($si <= 2 ** ($bits - 1))) {
            // positive or zero
            if ($si >= 0) {
                $bin = base_convert($si, 10, 2);
                // pad to $bits bit
                $bin_length = \strlen($bin);
                if ($bin_length < $bits) {
                    $bin = str_repeat('0', $bits - $bin_length).$bin;
                }
            } else {
                // negative
                $si = -$si - 2 ** $bits;
                $bin = base_convert($si, 10, 2);
                $bin_length = \strlen($bin);
                if ($bin_length > $bits) {
                    $bin = str_repeat('1', $bits - $bin_length).$bin;
                }
            }
        }

        return $bin;
    }

    /**
     * Send our auth message and returns the response.
     *
     * @return string SMTP Response
     */
    protected function sendMessage1(EsmtpTransport $client): string
    {
        $message = $this->createMessage1();

        return $client->executeCommand(sprintf("AUTH %s %s\r\n", $this->getAuthKeyword(), base64_encode($message)), [334]);
    }

    /**
     * Fetch all details of our response (message 2).
     *
     * @return array our response parsed
     */
    protected function parseMessage2(string $response): array
    {
        $responseHex = bin2hex($response);
        $length = floor(hexdec(substr($responseHex, 28, 4)) / 256) * 2;
        $offset = floor(hexdec(substr($responseHex, 32, 4)) / 256) * 2;
        $challenge = hex2bin(substr($responseHex, 48, 16));
        $context = hex2bin(substr($responseHex, 64, 16));
        $targetInfoH = hex2bin(substr($responseHex, 80, 16));
        $targetName = hex2bin(substr($responseHex, $offset, $length));
        $offset = floor(hexdec(substr($responseHex, 88, 4)) / 256) * 2;
        $targetInfoBlock = substr($responseHex, $offset);
        [$domainName, $serverName, $DNSDomainName, $DNSServerName, $terminatorByte] = $this->readSubBlock($targetInfoBlock);

        return [
            $challenge,
            $context,
            $targetInfoH,
            $targetName,
            $domainName,
            $serverName,
            $DNSDomainName,
            $DNSServerName,
            hex2bin($targetInfoBlock),
            $terminatorByte,
        ];
    }

    /**
     * Read the blob information in from message2.
     */
    protected function readSubBlock(string $block): array
    {
        // remove terminatorByte cause it's always the same
        $block = substr($block, 0, -8);

        $length = \strlen($block);
        $offset = 0;
        $data = [];
        while ($offset < $length) {
            $blockLength = hexdec(substr(substr($block, $offset, 8), -4)) / 256;
            $offset += 8;
            $data[] = hex2bin(substr($block, $offset, $blockLength * 2));
            $offset += $blockLength * 2;
        }

        if (3 == \count($data)) {
            $data[] = $data[2];
            $data[2] = '';
        }

        $data[] = $this->createByte('00');

        return $data;
    }

    /**
     * Send our final message with all our data.
     *
     * @param string $response Message 1 response (message 2)
     * @param bool   $v2       Use version2 of the protocol
     *
     * @throws Exception
     */
    protected function sendMessage3(string $response, EsmtpTransport $client, bool $v2 = true): string
    {
        $timestamp = $this->getCorrectTimestamp(bcmul(microtime(true), '1000'));
        $nonce = random_bytes(8);

        [$domain, $username] = $this->getDomainAndUsername($client->getUsername());
        // $challenge, $context, $targetInfoH, $targetName, $domainName, $workstation, $DNSDomainName, $DNSServerName, $blob, $ter
        [$challenge, , , , , $workstation, , , $blob] = $this->parseMessage2($response);

        if (!$v2) {
            // LMv1
            $lmResponse = $this->createLMPassword($client->getPassword(), $challenge);
            // NTLMv1
            $ntlmResponse = $this->createNTLMPassword($client->getPassword(), $challenge);
        } else {
            // LMv2
            $lmResponse = $this->createLMv2Password($client->getPassword(), $username, $domain, $challenge, $nonce);
            // NTLMv2
            $ntlmResponse = $this->createNTLMv2Hash($client->getPassword(), $username, $domain, $challenge, $blob, $timestamp, $nonce);
        }

        $message = $this->createMessage3($domain, $username, $workstation, $lmResponse, $ntlmResponse);

        return $client->executeCommand(sprintf("%s\r\n", base64_encode($message)), [235]);
    }

    /**
     * Create our message 1.
     */
    protected function createMessage1(): string
    {
        return self::NTLMSIG
            .$this->createByte('01') // Message 1
            .$this->createByte('0702'); // Flags
    }

    /**
     * Create our message 3.
     *
     * @param string $domain
     * @param string $username
     * @param string $workstation
     * @param string $lmResponse
     * @param string $ntlmResponse
     */
    protected function createMessage3($domain, $username, $workstation, $lmResponse, $ntlmResponse): string
    {
        // Create security buffers
        $domainSec = $this->createSecurityBuffer($domain, 64);
        $domainInfo = $this->readSecurityBuffer(bin2hex($domainSec));
        $userSec = $this->createSecurityBuffer($username, ($domainInfo[0] + $domainInfo[1]) / 2);
        $userInfo = $this->readSecurityBuffer(bin2hex($userSec));
        $workSec = $this->createSecurityBuffer($workstation, ($userInfo[0] + $userInfo[1]) / 2);
        $workInfo = $this->readSecurityBuffer(bin2hex($workSec));
        $lmSec = $this->createSecurityBuffer($lmResponse, ($workInfo[0] + $workInfo[1]) / 2, true);
        $lmInfo = $this->readSecurityBuffer(bin2hex($lmSec));
        $ntlmSec = $this->createSecurityBuffer($ntlmResponse, ($lmInfo[0] + $lmInfo[1]) / 2, true);

        return self::NTLMSIG
            .$this->createByte('03') // TYPE 3 message
            .$lmSec // LM response header
            .$ntlmSec // NTLM response header
            .$domainSec // Domain header
            .$userSec // User header
            .$workSec // Workstation header
            .$this->createByte('000000009a', 8) // session key header (empty)
            .$this->createByte('01020000') // FLAGS
            .$this->convertTo16bit($domain) // domain name
            .$this->convertTo16bit($username) // username
            .$this->convertTo16bit($workstation) // workstation
            .$lmResponse
            .$ntlmResponse;
    }

    /**
     * @param string $timestamp  Epoch timestamp in microseconds
     * @param string $client     Random bytes
     * @param string $targetInfo
     */
    protected function createBlob($timestamp, $client, $targetInfo): string
    {
        return $this->createByte('0101')
            .$this->createByte('00')
            .$timestamp
            .$client
            .$this->createByte('00')
            .$targetInfo
            .$this->createByte('00');
    }

    /**
     * Get domain and username from our username.
     *
     * @example DOMAIN\username
     */
    protected function getDomainAndUsername(string $name): array
    {
        if (str_contains($name, '\\')) {
            return explode('\\', $name);
        }

        if (str_contains($name, '@')) {
            [$user, $domain] = explode('@', $name);

            return [$domain, $user];
        }

        // no domain passed
        return ['', $name];
    }

    /**
     * Create LMv1 response.
     */
    protected function createLMPassword(string $password, string $challenge): string
    {
        // FIRST PART
        $password = $this->createByte(strtoupper($password), 14, false);
        [$key1, $key2] = str_split($password, 7);

        $desKey1 = $this->createDesKey($key1);
        $desKey2 = $this->createDesKey($key2);

        $constantDecrypt = $this->createByte($this->desEncrypt(self::DESCONST, $desKey1).$this->desEncrypt(self::DESCONST, $desKey2), 21, false);

        // SECOND PART
        return $this->createPasswordStep2($constantDecrypt, $challenge);
    }

    /**
     * Create NTLMv1 response.
     */
    protected function createNTLMPassword(string $password, string $challenge): string
    {
        // FIRST PART
        $ntlmHash = $this->createByte($this->md4Encrypt($password), 21, false);

        return $this->createPasswordStep2($ntlmHash, $challenge);
    }

    /**
     * Convert a normal timestamp to a tenth of a microtime epoch time.
     */
    protected function getCorrectTimestamp(string $time): string
    {
        // Get our timestamp (tricky!)
        $time = number_format($time, 0, '.', ''); // save microtime to string
        $time = bcadd($time, '11644473600000', 0); // add epoch time
        $time = bcmul($time, 10000, 0); // tenths of a microsecond.

        $binary = $this->si2bin($time, 64); // create 64 bit binary string
        $timestamp = '';
        for ($i = 0; $i < 8; ++$i) {
            $timestamp .= \chr(bindec(substr($binary, -(($i + 1) * 8), 8)));
        }

        return $timestamp;
    }

    /**
     * Create LMv2 response.
     *
     * @param string $challenge NTLM Challenge
     */
    protected function createLMv2Password(string $password, string $username, string $domain, string $challenge, string $nonce): string
    {
        $lmPass = '00'; // by default 00
        // if $password > 15 than we can't use this method
        if (\strlen($password) <= 15) {
            $ntlmHash = $this->md4Encrypt($password);
            $ntml2Hash = $this->md5Encrypt($ntlmHash, $this->convertTo16bit(strtoupper($username).$domain));

            $lmPass = bin2hex($this->md5Encrypt($ntml2Hash, $challenge.$nonce).$nonce);
        }

        return $this->createByte($lmPass, 24);
    }

    /**
     * Create NTLMv2 response.
     *
     * @param string $challenge  Hex values
     * @param string $targetInfo Hex values
     * @param string $client     Random bytes
     *
     * @see http://davenport.sourceforge.net/ntlm.html#theNtlmResponse
     */
    protected function createNTLMv2Hash(string $password, string $username, string $domain, string $challenge, string $targetInfo, string $timestamp, string $client): string
    {
        $ntlmHash = $this->md4Encrypt($password);
        $ntml2Hash = $this->md5Encrypt($ntlmHash, $this->convertTo16bit(strtoupper($username).$domain));

        // create blob
        $blob = $this->createBlob($timestamp, $client, $targetInfo);

        $ntlmv2Response = $this->md5Encrypt($ntml2Hash, $challenge.$blob);

        return $ntlmv2Response.$blob;
    }

    protected function createDesKey($key): bool|string
    {
        $material = [bin2hex($key[0])];
        $len = \strlen($key);
        for ($i = 1; $i < $len; ++$i) {
            [$high, $low] = str_split(bin2hex($key[$i]));
            $v = $this->castToByte(\ord($key[$i - 1]) << (7 + 1 - $i) | $this->uRShift(hexdec(dechex(hexdec($high) & 0xF).dechex(hexdec($low) & 0xF)), $i));
            $material[] = str_pad(substr(dechex($v), -2), 2, '0', \STR_PAD_LEFT); // cast to byte
        }
        $material[] = str_pad(substr(dechex($this->castToByte(\ord($key[6]) << 1)), -2), 2, '0');

        // odd parity
        foreach ($material as $k => $v) {
            $b = $this->castToByte(hexdec($v));
            $needsParity = 0 == (($this->uRShift($b, 7) ^ $this->uRShift($b, 6) ^ $this->uRShift($b, 5)
                        ^ $this->uRShift($b, 4) ^ $this->uRShift($b, 3) ^ $this->uRShift($b, 2)
                        ^ $this->uRShift($b, 1)) & 0x01);

            [$high, $low] = str_split($v);
            if ($needsParity) {
                $material[$k] = dechex(hexdec($high) | 0x0).dechex(hexdec($low) | 0x1);
            } else {
                $material[$k] = dechex(hexdec($high) & 0xF).dechex(hexdec($low) & 0xE);
            }
        }

        return hex2bin(implode('', $material));
    }

    /** HELPER FUNCTIONS */

    /**
     * Create our security buffer depending on length and offset.
     *
     * @param string $value  Value we want to put in
     * @param int    $offset start of value
     * @param bool   $is16   Do we 16bit string or not?
     */
    protected function createSecurityBuffer(string $value, int $offset, bool $is16 = false): string
    {
        $length = \strlen(bin2hex($value));
        $length = $is16 ? $length / 2 : $length;
        $length = $this->createByte(str_pad(dechex($length), 2, '0', \STR_PAD_LEFT), 2);

        return $length.$length.$this->createByte(dechex($offset), 4);
    }

    /**
     * Read our security buffer to fetch length and offset of our value.
     *
     * @param string $value Securitybuffer in hex
     *
     * @return array array with length and offset
     */
    protected function readSecurityBuffer(string $value): array
    {
        $length = floor(hexdec(substr($value, 0, 4)) / 256) * 2;
        $offset = floor(hexdec(substr($value, 8, 4)) / 256) * 2;

        return [$length, $offset];
    }

    /**
     * Cast to byte java equivalent to (byte).
     */
    protected function castToByte(int $v): int
    {
        return (($v + 128) % 256) - 128;
    }

    /**
     * Java unsigned right bitwise
     * $a >>> $b.
     */
    protected function uRShift(int $a, int $b): int
    {
        if (0 == $b) {
            return $a;
        }

        return ($a >> $b) & ~(1 << (8 * \PHP_INT_SIZE - 1) >> ($b - 1));
    }

    /**
     * Right padding with 0 to certain length.
     *
     * @param int  $bytes Length of bytes
     * @param bool $isHex Did we provided hex value
     */
    protected function createByte(string $input, int $bytes = 4, bool $isHex = true): string
    {
        if ($isHex) {
            $byte = hex2bin(str_pad($input, $bytes * 2, '00'));
        } else {
            $byte = str_pad($input, $bytes, "\x00");
        }

        return $byte;
    }

    /** ENCRYPTION ALGORITHMS */

    /**
     * DES Encryption.
     *
     * @param string $value An 8-byte string
     */
    protected function desEncrypt(string $value, string $key): string
    {
        return substr(openssl_encrypt($value, 'DES-ECB', $key, \OPENSSL_RAW_DATA), 0, 8);
    }

    /**
     * MD5 Encryption.
     *
     * @param string $key Encryption key
     * @param string $msg Message to encrypt
     */
    protected function md5Encrypt(string $key, string $msg): string
    {
        $blocksize = 64;
        if (\strlen($key) > $blocksize) {
            $key = pack('H*', md5($key));
        }

        $key = str_pad($key, $blocksize, "\0");
        $ipadk = $key ^ str_repeat("\x36", $blocksize);
        $opadk = $key ^ str_repeat("\x5c", $blocksize);

        return pack('H*', md5($opadk.pack('H*', md5($ipadk.$msg))));
    }

    /**
     * MD4 Encryption.
     *
     * @see https://secure.php.net/manual/en/ref.hash.php
     */
    protected function md4Encrypt(string $input): string
    {
        $input = $this->convertTo16bit($input);

        return \function_exists('hash') ? hex2bin(hash('md4', $input)) : mhash(\MHASH_MD4, $input);
    }

    /**
     * Convert UTF-8 to UTF-16.
     */
    protected function convertTo16bit(string $input): string
    {
        return iconv('UTF-8', 'UTF-16LE', $input);
    }

    protected function debug(string $message)
    {
        $message = bin2hex($message);
        $messageId = substr($message, 16, 8);
        echo substr($message, 0, 16)." NTLMSSP Signature<br />\n";
        echo $messageId." Type Indicator<br />\n";

        if ('02000000' == $messageId) {
            $map = [
                'Challenge',
                'Context',
                'Target Information Security Buffer',
                'Target Name Data',
                'NetBIOS Domain Name',
                'NetBIOS Server Name',
                'DNS Domain Name',
                'DNS Server Name',
                'BLOB',
                'Target Information Terminator',
            ];

            $data = $this->parseMessage2(hex2bin($message));

            foreach ($map as $key => $value) {
                echo bin2hex($data[$key]).' - '.$data[$key].' ||| '.$value."<br />\n";
            }
        } elseif ('03000000' == $messageId) {
            $i = 0;
            $data[$i++] = substr($message, 24, 16);
            [$lmLength, $lmOffset] = $this->readSecurityBuffer($data[$i - 1]);

            $data[$i++] = substr($message, 40, 16);
            [$ntmlLength, $ntmlOffset] = $this->readSecurityBuffer($data[$i - 1]);

            $data[$i++] = substr($message, 56, 16);
            [$targetLength, $targetOffset] = $this->readSecurityBuffer($data[$i - 1]);

            $data[$i++] = substr($message, 72, 16);
            [$userLength, $userOffset] = $this->readSecurityBuffer($data[$i - 1]);

            $data[$i++] = substr($message, 88, 16);
            [$workLength, $workOffset] = $this->readSecurityBuffer($data[$i - 1]);

            $data[$i++] = substr($message, 104, 16);
            $data[$i++] = substr($message, 120, 8);
            $data[$i++] = substr($message, $targetOffset, $targetLength);
            $data[$i++] = substr($message, $userOffset, $userLength);
            $data[$i++] = substr($message, $workOffset, $workLength);
            $data[$i++] = substr($message, $lmOffset, $lmLength);
            $data[$i] = substr($message, $ntmlOffset, $ntmlLength);

            $map = [
                'LM Response Security Buffer',
                'NTLM Response Security Buffer',
                'Target Name Security Buffer',
                'User Name Security Buffer',
                'Workstation Name Security Buffer',
                'Session Key Security Buffer',
                'Flags',
                'Target Name Data',
                'User Name Data',
                'Workstation Name Data',
                'LM Response Data',
                'NTLM Response Data',
            ];

            foreach ($map as $key => $value) {
                echo $data[$key].' - '.hex2bin($data[$key]).' ||| '.$value."<br />\n";
            }
        }

        echo '<br /><br />';
    }

    protected function createPasswordStep2(string $ntlmHash, string $challenge): string
    {
        [$key1, $key2, $key3] = str_split($ntlmHash, 7);

        $desKey1 = $this->createDesKey($key1);
        $desKey2 = $this->createDesKey($key2);
        $desKey3 = $this->createDesKey($key3);

        return $this->desEncrypt($challenge, $desKey1).$this->desEncrypt($challenge, $desKey2).$this->desEncrypt($challenge, $desKey3);
    }
}
