<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Config\Resource\FileResource;

/**
 * IniFileLoader loads translations from an ini file.
 *
 * @author stealth35
 */
class IniFileLoader extends ArrayLoader implements LoaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function load($resource, $locale, $domain = 'messages')
    {
        if (!is_file($resource)) {
            throw new \InvalidArgumentException(sprintf('Error opening file "%s".', $resource));
        }

        $messages = parse_ini_file($resource, true);

        $catalogue = parent::load($messages, $locale, $domain);
        $catalogue->addResource(new FileResource($resource));

        return $catalogue;
    }
}
