<?php

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Config\Resource\FileResource;

class PoFileLoader extends ArrayLoader implements LoaderInterface {

	/**
	 * Magic used for validating the format of a MO file as well as
	 * detecting if the machine used to create that file was little endian.
	 *
	 * @see lithium\g11n\catalog\adapter\Gettext::_parseMo()
	 * @var float
	 */
	const MO_LITTLE_ENDIAN_MAGIC = 0x950412de;

	/**
	 * Magic used for validating the format of a MO file as well as
	 * detecting if the machine used to create that file was big endian.
	 *
	 * @see lithium\g11n\catalog\adapter\Gettext::_parseMo()
	 * @var float
	 */
	const MO_BIG_ENDIAN_MAGIC = 0xde120495;

	/**
	 * The size of the header of a MO file in bytes.
	 *
	 * @see lithium\g11n\catalog\adapter\Gettext::_parseMo()
	 * @var integer Number of bytes.
	 */
	const MO_HEADER_SIZE = 28;


	public function load($resource, $locale, $domain = 'messages') {

		$messages = $this->parse($resource);

        // empty file
        if (null === $messages) {
            $messages = array();
        }

        // not an array
        if (!is_array($messages)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" must contain a YAML array.', $resource));
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
	protected function parse($resource) {

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
			} elseif (substr($line, 0, 3) === '#~ ') {
				$item['flags']['obsolete'] = true;
			} elseif (substr($line, 0, 3) === '#, ') {
				$item['flags'][substr($line, 3)] = true;
			} elseif (substr($line, 0, 3) === '#: ') {
				$item['occurrences'][] = array(
					'file' => strtok(substr($line, 3), ':'),
					'line' => strtok(':')
				);
			} elseif (substr($line, 0, 3) === '#. ') {
				$item['comments'][] = substr($line, 3);
			} elseif ($line[0] === '#') {
				$item['comments'][] = ltrim(substr($line, 1));
			} elseif (substr($line, 0, 7) === 'msgid "') {
				$item['ids']['singular'] = substr($line, 7, -1);
			} elseif (substr($line, 0, 9) === 'msgctxt "') {
				$item['context'] = substr($line, 9, -1);
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

		return $this->merge($data, $item);
	}


	/**
	 * Reads an unsigned long from stream respecting endianess.
	 *
	 * @param resource $stream
	 * @param boolean $isBigEndian
	 * @return integer
	 */
	protected function _readLong($stream, $isBigEndian) {
		$result = unpack($isBigEndian ? 'N1' : 'V1', fread($stream, 4));
		$result = current($result);
		return (integer) substr($result, -8);
	}



	/**
	 * Merges an item into given data and unescapes fields.
	 *
	 * Please note that items with an id containing exclusively whitespace characters
	 * or are empty are **not** being merged. Whitespace characters are space, tab, vertical
	 * tab, line feed, carriage return and form feed.
	 *
	 * @see lithium\g11n\catalog\Adapter::merge()
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
        return $this->_merge($data, $item);
    }


	/**
	 * Merges an item into given data.
	 *
	 * @param array $data Data to merge item into.
	 * @param array $item Item to merge into $data. The item must have an `'id'` key.
	 * @return array The merged data.
	 */
	protected function _merge(array $data, array $item) {
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