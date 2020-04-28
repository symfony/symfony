<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Loco;

use Symfony\Component\Translation\Exception\LogicException;
use Symfony\Component\Translation\Exception\RemoteException;
use Symfony\Component\Translation\Message\MessageInterface;
use Symfony\Component\Translation\Message\SmsMessage;
use Symfony\Component\Translation\Remote\AbstractRemote;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 */
final class LocoRemote extends AbstractRemote
{
    protected const HOST = 'rest.loco.com';

    private $apiKey;

    public function __construct(string $apiKey, HttpClientInterface $client = null)
    {
        $this->apiKey = $apiKey;

        parent::__construct($client);
    }

    public function __toString(): string
    {
        return sprintf('loco://%s', $this->getEndpoint());
    }

    /**
     * {@inheritdoc}
     */
    public function write(TranslatorBag $translations): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function read(array $domains, array $locales): TranslatorBag
    {
        $response = $this->client->request('GET', sprintf('https://%s/api/export/all.xlf', $this->getEndpoint()), [
            'headers' => [
                'Authorization' => 'Loco '.$this->apiKey,
            ],
        ]);

        dump($response->getContent());
        die;

        if (1 === count($locales)) {
            $reponse = $this->client->request('GET', sprintf('%s/api/export/locale/%s.json', $this->getEndpoint(), $locales[0]), [
                'headers' => [
                    'Loco' => $this->apiKey,
                ],
            ]);

            dump($response->getContent());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(TranslatorBag $translations): void
    {
    }
}
