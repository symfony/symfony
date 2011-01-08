<?php

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Translation\Resource\FileResource;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * CsvFileLoader loads translations from CSV files.
 *
 * @author Saša Stamenković <umpirsky@gmail.com>
 */
class CsvFileLoader extends ArrayLoader implements LoaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function load($resource, $locale, $domain = 'messages')
    {
        $messages = array();
        $file = @fopen($resource, 'rb');
        if (!$file) {
            throw new \InvalidArgumentException(sprintf('Error opening file "%s".', $resource));
        }

        while(($data = fgetcsv($file, 0, ';')) !== false) {
            if (substr($data[0], 0, 1) === '#') {
                continue;
            }

            if (!isset($data[1])) {
                continue;
            }

            if (count($data) == 2) {
                $messages[$data[0]] = $data[1];
            } else {
                 continue;
            }
        }

        $catalogue = parent::load($messages, $locale, $domain);
        $catalogue->addResource(new FileResource($resource));

        return $catalogue;
    }
}
