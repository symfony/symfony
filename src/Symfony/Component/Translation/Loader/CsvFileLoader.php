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
    private string $delimiter = ';';
    private string $enclosure = '"';

    protected function loadResource(string $resource): array
    {
        $messages = [];

        if (!$file = @fopen($resource, 'r')) {
            throw new NotFoundResourceException(\sprintf('Error opening file "%s".', $resource));
        }

        try {
            while (false !== $data = fgetcsv($file, null, $this->delimiter, $this->enclosure, '')) {
                // empty lines are read as [null]
                if (isset($data[1]) && 2 === \count($data) && !str_starts_with($data[0], '#')) {
                    $messages[$data[0]] = $data[1];
                }
            }
        } finally {
            fclose($file);
        }

        return $messages;
    }

    /**
     * Sets the delimiter and enclosure character for CSV.
     */
    public function setCsvControl(string $delimiter = ';', string $enclosure = '"'): void
    {
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
    }
}
