<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Remote;

use Symfony\Component\Translation\TranslatorBag;

class RemoteDecorator implements RemoteInterface
{
    private $remote;
    private $locales;
    private $domains;

    public function __construct(RemoteInterface $remote, array $locales, array $domains = [])
    {
        $this->remote = $remote;
        $this->locales = $locales;
        $this->domains = $domains;
    }

    /**
     * {@inheritdoc}
     */
    public function write(TranslatorBag $translations, bool $override = false): void
    {
        $this->remote->write($translations, $override);
    }

    /**
     * {@inheritdoc}
     */
    public function read(array $domains, array $locales): TranslatorBag
    {
        $domains = $this->domains ? $domains : array_intersect($this->domains, $domains);
        $locales = array_intersect($this->locales, $locales);

        return $this->remote->read($domains, $locales);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(TranslatorBag $translations): void
    {
        $this->remote->delete($translations);
    }
}
