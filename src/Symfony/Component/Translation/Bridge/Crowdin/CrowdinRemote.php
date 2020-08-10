<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Crowdin;

use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Remote\AbstractRemote;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.2
 * @final
 *
 * In Crowdin:
 */
class CrowdinRemote extends AbstractRemote
{
    protected const HOST = 'crowdin.com/api/v2';

    private $apiKey;
    private $loader;
    private $defaultLocale;

    public function __construct(string $apiKey, HttpClientInterface $client = null, LoaderInterface $loader = null, string $defaultLocale = null)
    {
        $this->apiKey = $apiKey;
        $this->loader = $loader;
        $this->defaultLocale = $defaultLocale;

        parent::__construct($client);
    }

    public function __toString(): string
    {
        return sprintf('crowdin://%s', $this->getEndpoint());
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
