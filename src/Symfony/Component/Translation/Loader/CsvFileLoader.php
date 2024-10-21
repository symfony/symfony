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

use Symfony\Component\Translation\Exception\NotFoundResourceException;

/**
 * CsvFileLoader loads translations from CSV files.
 *
 * @author Saša Stamenković <umpirsky@gmail.com>
 */
class CsvFileLoader extends FileLoader
{
    private $delimiter = ';';
    private $enclosure = '"';
    private $escape = '';

    /**
     * {@inheritdoc}
     */
    protected function loadResource(string $resource)
    {
        $messages = [];

        try {
            $file = new \SplFileObject($resource, 'rb');
        } catch (\RuntimeException $e) {
            throw new NotFoundResourceException(sprintf('Error opening file "%s".', $resource), 0, $e);
        }

        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl($this->delimiter, $this->enclosure, '' === $this->escape && \PHP_VERSION_ID < 70400 ? '\\' : $this->escape);

        foreach ($file as $data) {
            if (false === $data) {
                continue;
            }

            if ('#' !== substr($data[0], 0, 1) && isset($data[1]) && 2 === \count($data)) {
                $messages[$data[0]] = $data[1];
            }
        }

        return $messages;
    }

    /**
     * Sets the delimiter, enclosure, and escape character for CSV.
     */
    public function setCsvControl(string $delimiter = ';', string $enclosure = '"', string $escape = '')
    {
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escape = '' === $escape && \PHP_VERSION_ID < 70400 ? '\\' : $escape;
    }
}
