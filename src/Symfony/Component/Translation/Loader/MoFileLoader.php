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
	 * Reads data.
	 *
	 * @param string $category A category.
	 * @param string $locale A locale identifier.
	 * @param string $scope The scope for the current operation.
	 * @return array|void
	 */
	public function read($category, $locale, $scope) {
		$files = $this->_files($category, $locale, $scope);

		foreach ($files as $file) {
			$method = '_parse' . ucfirst(pathinfo($file, PATHINFO_EXTENSION));

			if (!file_exists($file) || !is_readable($file)) {
				continue;
			}
			$stream = fopen($file, 'rb');
			$data = $this->invokeMethod($method, array($stream));
			fclose($stream);

			if ($data) {
				return $data;
			}
		}
	}

	/**
	 * Writes data.
	 *
	 * @param string $category A category.
	 * @param string $locale A locale identifier.
	 * @param string $scope The scope for the current operation.
	 * @param array $data The data to write.
	 * @return boolean
	 */
	public function write($category, $locale, $scope, array $data) {
		$files = $this->_files($category, $locale, $scope);

		foreach ($files as $file) {
			$method = '_compile' . ucfirst(pathinfo($file, PATHINFO_EXTENSION));

			if (!$stream = fopen($file, 'wb')) {
				return false;
			}
			$this->invokeMethod($method, array($stream, $data));
			fclose($stream);
		}
		return true;
	}

	/**
	 * Returns absolute paths to files according to configuration.
	 *
	 * @param string $category
	 * @param string $locale
	 * @param string $scope
	 * @return array
	 */
	protected function _files($category, $locale, $scope) {
		$path = $this->_config['path'];
		$scope = $scope ?: 'default';

		if (($pos = strpos($category, 'Template')) !== false) {
			$category = substr($category, 0, $pos);
			return array("{$path}/{$category}_{$scope}.pot");
		}

		if ($category == 'message') {
			$category = 'messages';
		}
		$category = strtoupper($category);

		return array(
			"{$path}/{$locale}/LC_{$category}/{$scope}.mo",
			"{$path}/{$locale}/LC_{$category}/{$scope}.po"
		);
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
	 * Items with an empty id are ignored. For more information see `_merge()`.
	 *
	 * @param resource $stream
	 * @return array
	 */
	protected function _parsePo($stream) {
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
				$data = $this->_merge($data, $item);
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
		return $this->_merge($data, $item);
	}

	/**
	 * Parses portable object template (POT) format.
	 *
	 * @param resource $stream
	 * @return array
	 */
	protected function _parsePot($stream) {
		return $this->_parsePo($stream);
	}

	/**
	 * Parses machine object (MO) format, independent of the machine's endian it
	 * was created on. Both 32bit and 64bit systems are supported.
	 *
	 * @param resource $stream
	 * @return array
	 * @throws RangeException If stream content has an invalid format.
	 */
	protected function _parseMo($stream) {
		$stat = fstat($stream);

		if ($stat['size'] < self::MO_HEADER_SIZE) {
			throw new RangeException("MO stream content has an invalid format.");
		}
		$magic = unpack('V1', fread($stream, 4));
		$magic = hexdec(substr(dechex(current($magic)), -8));

		if ($magic == self::MO_LITTLE_ENDIAN_MAGIC) {
			$isBigEndian = false;
		} elseif ($magic == self::MO_BIG_ENDIAN_MAGIC) {
			$isBigEndian = true;
		} else {
			throw new RangeException("MO stream content has an invalid format.");
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
	 * Compiles data into portable object (PO) format.
	 *
	 * To improve portability accross libraries the header is generated according
	 * to the format of the output of `xgettext`. This means using the same names for
	 * placeholders as well as including an empty entry. The empty entry at the
	 * beginning aids in parsing the file as it _attracts_ the preceding comments and
	 * following metadata when parsed which could otherwise be mistaken as a continued
	 * translation. The only difference in the header format is the initial header which
	 * just features one line of text.
	 *
	 * @param resource $stream
	 * @param array $data
	 * @return boolean
	 */
	protected function _compilePo($stream, array $data) {
		$output[] = '# This file is distributed under the same license as the PACKAGE package.';
		$output[] = '#';
		$output[] = 'msgid ""';
		$output[] = 'msgstr ""';
		$output[] = '"Project-Id-Version: PACKAGE VERSION\n"';
		$output[] = '"POT-Creation-Date: YEAR-MO-DA HO:MI+ZONE\n"';
		$output[] = '"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"';
		$output[] = '"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"';
		$output[] = '"Language-Team: LANGUAGE <EMAIL@ADDRESS>\n"';
		$output[] = '"MIME-Version: 1.0\n"';
		$output[] = '"Content-Type: text/plain; charset=UTF-8\n"';
		$output[] = '"Content-Transfer-Encoding: 8bit\n"';
		$output[] = '"Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\n"';
		$output[] = '';
		$output = implode("\n", $output) . "\n";
		fwrite($stream, $output);

		foreach ($data as $key => $item) {
			$output = array();
			$item = $this->_prepareForWrite($item);

			foreach ($item['occurrences'] as $occurrence) {
				$output[] = "#: {$occurrence['file']}:{$occurrence['line']}";
			}
			foreach ($item['comments'] as $comment) {
				$output[] = "#. {$comment}";
			}
			foreach ($item['flags'] as $flag => $value) {
				$output[] = "#, {$flag}";
			}
			$output[] = "msgid \"{$item['ids']['singular']}\"";

			if (isset($item['ids']['plural'])) {
				$output[] = "msgid_plural \"{$item['ids']['plural']}\"";

				foreach ((array) $item['translated'] ?: array(null, null) as $key => $value) {
					$output[] = "msgstr[{$key}] \"{$value}\"";
				}
			} else {
				if (is_array($item['translated'])) {
					$item['translated'] = array_pop($item['translated']);
				}
				$output[] = "msgstr \"{$item['translated']}\"";
			}
			$output[] = '';
			$output = implode("\n", $output) . "\n";
			fwrite($stream, $output);
		}
		return true;
	}

	/**
	 * Compiles data into portable object template (POT) format.
	 *
	 * @param resource $stream
	 * @param array $data
	 * @param array $meta
	 * @return boolean Success.
	 */
	protected function _compilePot($stream, array $data) {
		return $this->_compilePo($stream, $data);
	}

	/**
	 * Compiles data into machine object (MO) format.
	 *
	 * @param resource $stream
	 * @param array $data
	 * @return void
	 * @todo Determine if needed and implement compiler.
	 */
	protected function _compileMo($stream, array $data) {}

	/**
	 * Prepares an item before it is being written and escapes fields.
	 *
	 * All characters from \000 to \037 (this includes new line and tab characters)
	 * as well as the backslash (`\`) and the double quote (`"`) are escaped.
	 *
	 * Literal Windows CRLFs (`\r\n`) are converted to LFs (`\n`) to improve cross platform
	 * compatibility. Escaped single quotes (`'`) are unescaped as they should not need to be.
	 * Double escaped characters are maintained and not escaped once again.
	 *
	 * @link http://www.asciitable.com
	 * @see lithium\g11n\catalog\Adapter::_prepareForWrite()
	 * @param array $item
	 * @return array
	 */
	protected function _prepareForWrite(array $item) {
		$filter = function ($value) use (&$filter) {
			if (is_array($value)) {
				return array_map($filter, $value);
			}
			$value = strtr($value, array("\\'" => "'", "\\\\" => "\\", "\r\n" => "\n"));
			$value = addcslashes($value, "\0..\37\\\"");
			return $value;
		};
		$fields = array('id', 'ids', 'translated');

		foreach ($fields as $field) {
			if (isset($item[$field])) {
				$item[$field] = $filter($item[$field]);
			}
		}
		if (!isset($item['ids']['singular'])) {
			$item['ids']['singular'] = $item['id'];
		}
		if (isset($item['occurrences'])) {
			foreach ($item['occurrences'] as &$occurrence) {
				$occurrence['file'] = str_replace(LITHIUM_APP_PATH, '', $occurrence['file']);
			}
		}
		return parent::_prepareForWrite($item);
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