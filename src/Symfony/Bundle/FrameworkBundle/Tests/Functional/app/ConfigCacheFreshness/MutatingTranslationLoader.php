<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness;

use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;

class MutatingTranslationLoader implements LoaderInterface
{
    public function __construct(private LoaderInterface $inner)
    {
    }

    /**
     * Saves a translation after its loader returns and before the catalogue metadata is written.
     */
    public function load(mixed $resource, string $locale, string $domain = 'messages'): MessageCatalogue
    {
        $catalogue = $this->inner->load($resource, $locale, $domain);
        if ('translation-directory-remove' === Mutation::$mode && 'removed' === $domain && 'fr' === $locale && !is_file(Mutation::$directory.'/changed')) {
            unlink($resource);
            file_put_contents(Mutation::$directory.'/changed', $resource);
            clearstatcache();
        }
        if ('probe' === $domain && 'en' === $locale) {
            if (str_starts_with(Mutation::$mode, 'translation-directory')) {
                if (!is_file(Mutation::$directory.'/changed')) {
                    if ('translation-directory-add' === Mutation::$mode) {
                        file_put_contents(Mutation::$directory.'/translations/added.en.yaml', 'key: v2');
                    }
                    if ('translation-directory-remove' !== Mutation::$mode) {
                        file_put_contents(Mutation::$directory.'/changed', Mutation::$mode);
                    }
                    clearstatcache();
                }
            } else {
                Mutation::change($resource);
            }
        }

        return $catalogue;
    }
}
