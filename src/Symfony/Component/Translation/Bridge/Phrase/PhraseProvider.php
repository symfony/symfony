<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Phrase;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Exception\TransportException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProvider;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.2
 *
 * In Phrase:
 */
final class PhraseProvider extends AbstractProvider
{
    protected const HOST = 'api.phrase.com/v2';

    private $apiKey;
    private $loader;
    private $logger;
    private $defaultLocale;

    public function __construct(string $apiKey, HttpClientInterface $client = null, LoaderInterface $loader = null, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        $this->apiKey = $apiKey;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;

        parent::__construct($client);
    }

    public function __toString(): string
    {
        return sprintf('phrase://%s', $this->getEndpoint());
    }

    public function write(TranslatorBag $translations, bool $override = false): void
    {
        // TODO: Implement write() method.
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        // TODO: Implement read() method.
    }

    public function delete(TranslatorBag $translations): void
    {
        // TODO: Implement delete() method.
    }
}
