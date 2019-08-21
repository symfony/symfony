<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Credential;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Exception\RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Based on: aws-sdk-php / Credentials/InstanceProfileProvider.php.
 *
 * @author Karoly Gossler <connor@connor.hu>
 */
class InstanceCredentialProvider
{
    const SERVER_URI_TEMPLATE = 'http://169.254.169.254/latest/meta-data/iam/security-credentials/%role_name%';

    public function __construct(HttpClientInterface $client = null, int $retries = 3)
    {
        $this->retries = $retries;
        $this->client = $client;

        if (null === $this->client) {
            if (!class_exists(HttpClient::class)) {
                throw new LogicException(sprintf('You cannot use "%s" as the HttpClient component is not installed. Try running "composer require symfony/http-client".', __CLASS__));
            }

            $this->client = HttpClient::create();
        }
    }

    public function getCredential(string $roleName): ApiTokenCredential
    {
        $attempts = 0;

        $instanceMetadataServerURL = str_replace('%role_name%', $roleName, self::SERVER_URI_TEMPLATE);

        while (true) {
            try {
                ++$attempts;

                $response = $this->client->request('GET', $instanceMetadataServerURL);

                if (200 === $response->getStatusCode()) {
                    $content = json_decode($response->getContent(), true);

                    if (null === $content) {
                        throw new RuntimeException('Unexpected instance metadata response.');
                    }

                    if ('Success' !== $content['Code']) {
                        $msg = sprintf('Unexpected instance profile response: %s', $content['Code']);
                        throw new RuntimeException($msg);
                    }

                    return new ApiTokenCredential($content['AccessKeyId'], $content['SecretAccessKey'], $content['Token'], new \DateTime($content['Expiration']));
                } elseif (404 === $response->getStatusCode()) {
                    $attempts = $this->retries + 1;
                }

                sleep(pow(1.2, $attempts));
            } catch (\Exception $e) {
            }

            if ($attempts > $this->retries) {
                throw new RuntimeException('Error retrieving credentials from instance metadata server.');
            }
        }
    }
}
