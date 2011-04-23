<?php

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Config\Resource\FileResource;

class MoFileLoader implements LoaderInterface {

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


	}

	/**
	 * Parses machine object (MO) format, independent of the machine's endian it
	 * was created on. Both 32bit and 64bit systems are supported.
	 *
	 * @param resource $stream
	 * @return array
	 * @throws RangeException If stream content has an invalid format.
	 */
	protected function _parseMo($resource) {

		$stream = fopen($resource, 'r+');

		$stat = fstat($stream);

		if ($stat['size'] < self::MO_HEADER_SIZE) {
			throw new \InvalidArgumentException("MO stream content has an invalid format.");
		}
		$magic = unpack('V1', fread($stream, 4));
		$magic = hexdec(substr(dechex(current($magic)), -8));

		if ($magic == self::MO_LITTLE_ENDIAN_MAGIC) {
			$isBigEndian = false;
		} elseif ($magic == self::MO_BIG_ENDIAN_MAGIC) {
			$isBigEndian = true;
		} else {
			throw new \InvalidArgumentException("MO stream content has an invalid format.");
		}

		$header = array(
			'formatRevision' => null,
			'count' => null,
			'offsetId' => null,
			'offsetTranslated' => null,
			'sizeHashes' => null,
			'offsetHashes' => null,
		);
		foreach ($header as &$value) {
			$value = $this->_readLong($stream, $isBigEndian);
		}
		extract($header);
		$data = array();

		for ($i = 0; $i < $count; $i++) {
			$singularId = $pluralId = null;
			$translated = null;

			fseek($stream, $offsetId + $i * 8);

			$length = $this->_readLong($stream, $isBigEndian);
			$offset = $this->_readLong($stream, $isBigEndian);

			if ($length < 1) {
				continue;
			}

			fseek($stream, $offset);
			$singularId = fread($stream, $length);

			if (strpos($singularId, "\000") !== false) {
				list($singularId, $pluralId) = explode("\000", $singularId);
			}

			fseek($stream, $offsetTranslated + $i * 8);
			$length = $this->_readLong($stream, $isBigEndian);
			$offset = $this->_readLong($stream, $isBigEndian);

			fseek($stream, $offset);
			$translated = fread($stream, $length);

			if (strpos($translated, "\000") !== false) {
				$translated = explode("\000", $translated);
			}

			$ids = array('singular' => $singularId, 'plural' => $pluralId);
			$data = $this->_merge($data, compact('ids', 'translated'));
		}

		fclose($stream);

		return $data;
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
	 * @see lithium\g11n\catalog\Adapter::_merge()
	 * @param array $data Data to merge item into.
	 * @param array $item Item to merge into $data.
	 * @return array The merged data.
	 */
	protected function _merge(array $data, array $item) {
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
        return parent::_merge($data, $item);
    }
}