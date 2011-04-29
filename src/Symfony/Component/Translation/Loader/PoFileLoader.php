<?php

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Config\Resource\FileResource;

class PoFileLoader extends ArrayLoader implements LoaderInterface {

    public function load($resource, $locale, $domain = 'messages') {

        $messages = $this->parse($resource);

        // empty file
        if (null === $messages) {
            $messages = array();
        }

        // not an array
        if (!is_array($messages)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" must contain a valid pot file.', $resource));
        }

        $catalogue = parent::load($messages, $locale, $domain);
        $catalogue->addResource(new FileResource($resource));

        return $catalogue;
    }

    /**
     * Parses portable object (PO) format.
     *
     * This parser sacrifices some features of the reference implementation the
     * differences to that implementation are as follows.
     * - No support for comments spanning multiple lines.
     * - Translator and extracted comments are treated as being the same type.
     * - Message IDs are allowed to have other encodings as just US-ASCII.
     *
     * Items with an empty id are ignored. For more information see `merge()`.
     *
     * @param resource $stream
     * @return array
     */
    public  function parse($resource) {

        $stream = fopen($resource, 'r+');

        $defaults = array(
            'ids' => array(),
            'translated' => null,
            'flags' => array(),
            'comments' => array(),
            'occurrences' => array()
        );
        $data = array();
        $item = $defaults;

        while ($line = fgets($stream)) {
            $line = trim($line);

            if ($line === '') {
                $data = $this->merge($data, $item);
                $item = $defaults;
            } elseif (substr($line, 0, 3) === '#: ') {
                $item['occurrences'][] = array(
                    'file' => strtok(substr($line, 3), ':'),
                    'line' => strtok(':')
                );
            } elseif (substr($line, 0, 7) === 'msgid "') {
                $item['ids']['singular'] = substr($line, 7, -1);
            } elseif (substr($line, 0, 8) === 'msgstr "') {
                $item['translated'] = substr($line, 8, -1);
            } elseif ($line[0] === '"') {
                $continues = isset($item['translated']) ? 'translated' : 'ids';

                if (is_array($item[$continues])) {
                    end($item[$continues]);
                    $item[$continues][key($item[$continues])] .= substr($line, 1, -1);
                } else {
                    $item[$continues] .= substr($line, 1, -1);
                }
            } elseif (substr($line, 0, 14) === 'msgid_plural "') {
                $item['ids']['plural'] = substr($line, 14, -1);
            } elseif (substr($line, 0, 7) === 'msgstr[') {
                $item['translated'][(integer) substr($line, 7, 1)] = substr($line, 11, -1);
            }
        }

        fclose($stream);

		$data = $this->merge($data, $item);

		foreach ($data as $id => $item) {

			$data[$id] = $item['translated'];
		}

		return $data;
    }

    /**
     * Merges an item into given data and unescapes fields.
     *
     * Please note that items with an id containing exclusively whitespace characters
     * or are empty are **not** being merged. Whitespace characters are space, tab, vertical
     * tab, line feed, carriage return and form feed.
     *
     * @param array $data Data to merge item into.
     * @param array $item Item to merge into $data.
     * @return array The merged data.
     */
    protected function merge(array $data, array $item) {
        $filter = function ($value) use (&$filter) {
            if (is_array($value)) {
                return array_map($filter, $value);
            }
            return stripcslashes($value);
        };
        $fields = array('id', 'ids', 'translated');

        foreach ($fields as $field) {
            if (isset($item[$field])) {
                $item[$field] = $filter($item[$field]);
            }
        }
        if (isset($item['ids']['singular'])) {
            $item['id'] = $item['ids']['singular'];
        }
        if (empty($item['id']) || ctype_space($item['id'])) {
            return $data;
        }

		if (!isset($item['id'])) {
            return $data;
        }
        $id = $item['id'];

        $defaults = array(
            'ids' => array(),
            'translated' => null,
            'flags' => array(),
            'comments' => array(),
            'occurrences' => array()
        );
        $item += $defaults;

        if (!isset($data[$id])) {
            $data[$id] = $item;
            return $data;
        }
        foreach (array('ids', 'flags', 'comments', 'occurrences') as $field) {
            $data[$id][$field] = array_merge($data[$id][$field], $item[$field]);
        }
        if (!isset($data[$id]['translated'])) {
            $data[$id]['translated'] = $item['translated'];
        } elseif (is_array($item['translated'])) {
            $data[$id]['translated'] = (array) $data[$id]['translated'] + $item['translated'];
        }
        return $data;
    }
 }