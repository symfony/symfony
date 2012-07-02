<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub;

use Symfony\Component\Locale\Locale;
use Symfony\Component\Locale\Exception\NotImplementedException;
use Symfony\Component\Locale\Exception\MethodNotImplementedException;
use Symfony\Component\Locale\Exception\MethodArgumentNotImplementedException;
use Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException;

/**
 * Provides a stub ResourceBundle
 *
 * @author stealth35
 */
class StubResourceBundle implements \Iterator, \ArrayAccess, \Countable
{
    const URES_STRING     = 0;
    const URES_BINARY     = 1;
    const URES_TABLE      = 2;
    const URES_INT        = 7;
    const URES_ARRAY      = 8;
    const URES_INT_VECTOR = 14;

    /**
     * Magic numbers to authenticate the data file
     */
    const MAGIC1 = 0xda;
    const MAGIC2 = 0x27;

    /**
     * contains URES_INDEX_TOP==the length of indexes[];
     * formatVersion==1: all bits contain the length of indexes[]
     * but the length is much less than 0xff;
     * formatVersion>1:
     * only bits  7..0 contain the length of indexes[],
     *      bits 31..8 are reserved and set to 0
     *
     * @var integer
     */
    const URES_INDEX_LENGTH = 0;

    /**
     * contains the top of the key strings,
     * same as the bottom of resources or UTF-16 strings, rounded up
     *
     * @var integer
     */
    const URES_INDEX_KEYS_TOP = 1;

    /**
     * contains the top of all resources
     *
     * @var integer
     */
    const URES_INDEX_RESOURCES_TOP = 2;

    /**
     * contains the top of the bundle
     * in case it were ever different from [2]
     *
     * @var integer
     */
    const URES_INDEX_BUNDLE_TOP = 3;

    /**
     * max. length of any table
     *
     * @var integer
     */
    const URES_INDEX_MAX_TABLE_LENGTH = 4;

    /**
     * attributes bit set, see URES_ATT_* (new in formatVersion 1.2)
     *
     * @var integer
     */
    const URES_INDEX_ATTRIBUTES = 5;

    /**
     * top of the 16-bit units (UTF-16 string v2 UChars, URES_TABLE16, URES_ARRAY16),
     * rounded up (new in formatVersion 2.0, ICU 4.4)
     *
     * @var integer
     */
    const URES_INDEX_16BIT_TOP = 6;

    /**
     * checksum of the pool bundle (new in formatVersion 2.0, ICU 4.4)
     *
     * @var integer
     */
    const URES_INDEX_POOL_CHECKSUM = 7;

    /**
     * contains the top of index
     *
     * @var integer
     */
    const URES_INDEX_TOP = 8;

    /**
     * Nofallback attribute, attribute bit 0 in indexes[URES_INDEX_ATTRIBUTES].
     * New in formatVersion 1.2 (ICU 3.6).
     *
     * If set, then this resource bundle is a standalone bundle.
     * If not set, then the bundle participates in locale fallback, eventually
     * all the way to the root bundle.
     * If indexes[] is missing or too short, then the attribute cannot be determined
     * reliably. Dependency checking should ignore such bundles, and loading should
     * use fallbacks.
     *
     * @var integer
     */
    const URES_ATT_NO_FALLBACK = 1;

    /**
     * Attributes for bundles that are, or use, a pool bundle.
     * A pool bundle provides key strings that are shared among several other bundles
     * to reduce their total size.
     * New in formatVersion 2 (ICU 4.4).
     */
    const URES_ATT_IS_POOL_BUNDLE = 2;
    const URES_ATT_USES_POOL_BUNDLE = 4;

    /**
     * The error code of the last operation
     *
     * @var integer
     */
    private $errorCode = StubIntl::U_ZERO_ERROR;

    /**
     * The error message of the last operation
     *
     * @var integer
     */
    private $errorMessage = 'U_ZERO_ERROR';

    private $rootRes;

    private $indexes;

    private $headerSize;
    private $bigendian;
    private $charset;
    private $charsize;


    private $stream;

    private $current;
    private $key;
    private $index;

    /**
     * Constructor
     *
     * @param  string     $locale   Locale for which the resources should be loaded (locale name, e.g. en_CA)
     * @param  bundlename $style    The directory where the data is stored or the name of the .dat file
     * @param  Boolean    $fallback Whether locale should match exactly or fallback to parent locale is allowed
     *
     * @see    http://www.php.net/manual/en/resourcebundle.create.php
     * @see    http://icu-project.org/apiref/icu4c/classResourceBundle.html#details
     * @see    http://source.icu-project.org/repos/icu/icu/trunk/source/common/uresdata.h
     *
     * @throws \RuntimeException        When .res cannot be loaded
     * @throws NotImplementedException  When other resource style try to be loaded (ex : .dat)
     */
    public function __construct($locale, $bundlename, $fallback = null)
    {
        if (!$locale) {
            $this->setError(StubIntl::U_MISSING_RESOURCE_ERROR, 'resourcebundle_ctor: Cannot load libICU resource bundle');
        }

        if (!is_dir($bundlename)) {
            throw new NotImplementedException(NotImplementedException::INTL_INSTALL_MESSAGE);
        }

        $file = sprintf('%s/%s.res', $bundlename, $locale);

        if (false === file_exists($file)) {
            if ($fallback) {
                $fallbacklocal = Locale::getFallbackLocale($locale);
                $file = sprintf('%s/%s.res', $bundlename, $fallbacklocal);
                if (false === file_exists($file)) {
                    $this->setError(StubIntl::U_MISSING_RESOURCE_ERROR, 'resourcebundle_ctor: Cannot load libICU resource bundle');
                }
            } else {
                $this->setError(StubIntl::U_MISSING_RESOURCE_ERROR, 'resourcebundle_ctor: Cannot load libICU resource bundle');
            }
        }

        if (filesize($file) < 32) {
            $this->setError(StubIntl::U_MISSING_RESOURCE_ERROR, 'resourcebundle_ctor: Cannot load libICU resource bundle');
        }

        $this->readHeader($file);

        if (StubIntl::isFailure($this->errorCode)) {
            throw new \RuntimeException($this->errorMessage);
        }

        $this->readData();
        $this->getResourceType($this->rootRes);
        $this->setError(StubIntl::U_ZERO_ERROR);
    }

    /**
     * Static constructor
     *
     * @param  string     $locale   Locale for which the resources should be loaded (locale name, e.g. en_CA)
     * @param  bundlename $style    The directory where the data is stored or the name of the .dat file
     * @param  Boolean    $fallback Whether locale should match exactly or fallback to parent locale is allowed
     *
     * @see    http://www.php.net/manual/en/resourcebundle.create.php
     * @see    http://icu-project.org/apiref/icu4c/classResourceBundle.html#details
     * @see    http://source.icu-project.org/repos/icu/icu/trunk/source/common/uresdata.h
     *
     * @throws MethodArgumentValueNotImplementedException  When $fallback is set
     * @throws \RuntimeException                           When .res cannot be loaded
     * @throws NotImplementedException                     When other resource style try to be loaded (ex : .dat)
     */
    static public function create($locale, $bundlename, $fallback = null)
    {
        return new self($locale, $bundlename, $fallback);
    }

    /**
     * Returns formatter's last error code.
     *
     * @return int  The error code from last formatter call
     *
     * @see    http://www.php.net/manual/en/resourcebundle.geterrorcode.php
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Returns formatter's last error message
     *
     * @return string  The error message from last formatter call
     *
     * @see    http://www.php.net/manual/en/resourcebundle.geterrormessage.php
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Get number of elements in the bundle
     *
     * @return int The number of elements in the bundle.
     *
     * @see http://www.php.net/manual/en/resourcebundle.count.php
     */
    public function count()
    {
        $type   = $this->getType($this->rootRes);
        $offset = $this->getOffset($this->rootRes);

        fseek($this->stream, $offset + $this->headerSize);

        $this->setError(StubIntl::U_ZERO_ERROR);

        return $type === self::URES_TABLE ? $this->readInt16() : $this->readInt();
    }

    /**
     * Get data from the bundle
     *
     * @return mixed Returns the data located at the index or NULL on error.
     *               Strings, integers and binary data strings are returned as corresponding PHP types,
     *               integer array is returned as PHP array,
     *               Complex types are returned as ResourceBundle object.
     *
     * @see http://www.php.net/manual/en/resourcebundle.get.php
     */
    public function get($index)
    {
        if (is_int($index)) {
            return $this->getByKey($index);
        }

        if (is_string($index)) {
            return $this->getByIndex($index);
        }

        $this->setError(StubIntl::U_ILLEGAL_ARGUMENT_ERROR, 'resourcebundle_get: index should be integer or string');
    }

    /**
     * Get supported locales
     *
     * @return array Returns the list of locales supported by the bundle.
     *
     * @see http://www.php.net/manual/en/resourcebundle.locales.php
     */
    static public function getLocales($bundlename = null)
    {
        if (!$bundlename) {
            StubIntl::setError(StubIntl::U_ILLEGAL_ARGUMENT_ERROR, 'resourcebundle_locales: unable to parse input params');

            return false;
        }

        if (false === is_file($bundlename.'/res_index.res') ) {
            StubIntl::setError(StubIntl::U_MISSING_RESOURCE_ERROR, 'Cannot fetch locales list');

            return false;
        }

        $resindex = new self('res_index', $bundlename);
        $installedLocales = $resindex['InstalledLocales'];

        if (!$installedLocales) {
            return false;
        }

        return array_keys(iterator_to_array($installedLocales));
    }

    /**
     * @return boolean
     */
    public function isBigEndian()
    {
        return $this->bigendian;
    }

    /**
     * @return string
     */
    public function getDataFormatId()
    {
        return $this->dataFormatId;
    }

    /**
     * @return string
     */
    public function getDataVersion()
    {
        return $this->dataVersion;
    }

    /**
     * @return string
     */
    public function getUnicodeVersion()
    {
        return $this->unicodeVersion;
    }

    /**
     * @return string
     */
    public function getCharset()
    {
        $charset = 'UTF-';

        $charsize = $this->charsize * 8;

        $charset .= $charsize;

        if ($this->charsize > 1) {
            $charset .= $this->bigendian ? 'BE' : 'LE';
        }

        return $charset;
    }

    // Iterator

    public function current()
    {
        if (null !== $this->key) {
            return $this->get($this->key);
        }
    }

    public function key()
    {
        return $this->index;
    }

    public function next()
    {
        ++$this->key;
    }

    public function rewind()
    {
        $this->key = 0;
        $this->current = false;
    }

    public function valid()
    {
        return $this->key < count($this);
    }

    // ArrayAccess

    public function offsetExists($offset)
    {
        trigger_error('Cannot use object of type ResourceBundle as array', E_USER_ERROR);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        trigger_error('Cannot use object of type ResourceBundle as array', E_USER_ERROR);
    }

    public function offsetUnset($offset)
    {
        trigger_error('Cannot use object of type ResourceBundle as array', E_USER_ERROR);
    }

    private function setError($code, $message = '')
    {
        StubIntl::setError($code, $message);

        $this->errorCode = StubIntl::getErrorCode();
        $this->errorMessage = StubIntl::getErrorMessage();
    }

     /**
      * Header format:
      *
      *  - Header size (char)
      *  - Magic number 1 (byte)
      *  - Magic number 2 (byte)
      *  - Rest of the header size (char)
      *  - Reserved word (char)
      *  - Big endian indicator (byte)
      *  - Character set family indicator (byte)
      *  - Size of a char (byte)
      *  - Reserved byte (byte)
      *  - Data format identifier (4 bytes), each ICU data has its own
      *    identifier to distinguish them. [0] major [1] minor
      *                                    [2] milli [3] micro
      *  - Data version (4 bytes), the change version of the ICU data
      *                             [0] major [1] minor [2] milli [3] micro
      *  - Unicode version (4 bytes) this ICU is based on.
      */
    private function readHeader($file)
    {
        $stream = fopen($file, 'rb');

        $headers = unpack(
            'v' . 'headerSize/' .
            'C' . 'magic1/'     .
            'C' . 'magic2/'     .
            'v' . 'restSize/'   .
            'v' . 'reserved/'   .
            'C' . 'bigendian/'  .
            'C' . 'charset/'    .
            'v' . 'charsize/'
            , fread($stream, 12)
        );

        extract($headers);

        if ($magic1 !== self::MAGIC1 || $magic2 !== self::MAGIC2) {
            $this->setError(StubIntl::U_MISSING_RESOURCE_ERROR, 'resourcebundle_ctor: Cannot load libICU resource bundle');
        }

        $dataFormatId   = fread($stream, 4);
        $dataVersion    = implode('.', unpack('C4', fread($stream, 4)));
        $unicodeVersion = implode('.', unpack('C4', fread($stream, 4)));

        $this->headerSize     = $headerSize;
        $this->bigendian      = (Boolean) $bigendian;
        $this->charset        = $charset;
        $this->charsize       = $charsize;
        $this->dataFormatId   = $dataFormatId;
        $this->dataVersion    = $dataVersion;
        $this->unicodeVersion = $unicodeVersion;

        $this->stream = $stream;
    }

    private function readData()
    {
        fseek($this->stream, 32);

        $rootRes = fread($this->stream, 4);

        $indexLength = $this->readInt();
        $indexes[] = $indexLength;

        for ($i = 1; $i < $indexLength; ++$i) {
            $indexes[] = $this->readInt();
        }

        if ($indexLength > self::URES_INDEX_ATTRIBUTES) {
            $attributes = $indexes[self::URES_INDEX_ATTRIBUTES];

            $noFallback     = (Boolean) ($attributes & self::URES_ATT_NO_FALLBACK);
            $isPoolBundle   = (Boolean) ($attributes & self::URES_ATT_IS_POOL_BUNDLE);
            $usesPoolBundle = (Boolean) ($attributes & self::URES_ATT_USES_POOL_BUNDLE);
        }

        $this->rootRes        = $rootRes;
        $this->indexes        = $indexes;
        $this->noFallback     = $noFallback;
        $this->isPoolBundle   = $isPoolBundle;
        $this->usesPoolBundle = $usesPoolBundle;
    }

    private function getByKey($key)
    {
        $type   = $this->getType($this->rootRes);
        $offset = $this->getOffset($this->rootRes);

        $length = $this->count();

        if ($key < 0 || $key > ($length - 1)) {
            $this->setError(StubIntl::U_MISSING_RESOURCE_ERROR, "Cannot load resource element $key");

            return;
        }

        if ($type === self::URES_TABLE) {
            fseek($this->stream, $key * 2, SEEK_CUR);

            $keyOffset = $this->readInt16() + $this->headerSize;
            $this->index = $this->getTableKey($keyOffset);

            fseek($this->stream, ($length - $key - 1) * 2, SEEK_CUR);

            $padding = ftell($this->stream) % 4;

            fseek($this->stream, $padding, SEEK_CUR);
        } elseif ($type === self::URES_ARRAY) {
            $this->index = $key;
        }

        fseek($this->stream, $key * 4, SEEK_CUR);

        return $this->getResourceType(fread($this->stream, 4));
    }

    private function getByIndex($index)
    {
        $offset = $this->getOffset($this->rootRes);

        fseek($this->stream, $offset + $this->headerSize);

        $length = $this->readInt16();

        for ($i = 0; $i < $length; ++$i) {
            $keyOffset = $this->readInt16() + $this->headerSize;

            if ($index === $this->getTableKey($keyOffset)) {
                return $this->getByKey($i);
            }
        }

        $this->setError(StubIntl::U_MISSING_RESOURCE_ERROR, "Cannot load resource element '$index'");
    }

    /**
     * @return int
     */
    private function getType($res)
    {
        $type = current(unpack('V', $res));

        return $type >= 0 ? $type >> 28 : (($type & 0x7fffffff) >> 28) | 8;
    }

    /**
     * @return int
     */
    private function getOffset($res)
    {
        $offset = (current(unpack('V', $res)) & 0x0fffffff) << 2;

        return $offset;
    }

    /**
     * @return mixed
     */
    private function getResourceType($res)
    {
        $type   = $this->getType($res);
        $offset = $this->getOffset($res);

        switch ($type) {
            case self::URES_STRING:
                $resource = $this->getString($offset);
                break;
            case self::URES_INT:
                $resource = $this->getInt($offset);
                break;
            case self::URES_TABLE:
            case self::URES_ARRAY:
                $resource = $this->getResource($res);
                break;
            case self::URES_INT_VECTOR:
                $resource = $this->getIntVector($offset);
                break;
            case self::URES_BINARY:
                $resource = $this->getBinary($offset);
                break;
            default:
                throw new NotImplementedException(NotImplementedException::INTL_INSTALL_MESSAGE);
        }

        $this->setError(StubIntl::U_ZERO_ERROR);

        return $resource;
    }

    /**
     * @return ResourceBundle
     */
    private function getResource($res)
    {
        $old = $this->rootRes;
        $this->rootRes = $res;

        $resource = clone $this;

        $this->rootRes = $old;

        return $resource;
    }

    /**
     * @return string
     */
    private function getString($offset)
    {
        if (0 === $offset) {
            return '';
        }

        fseek($this->stream, $offset + $this->headerSize);

        $size = $this->readInt();

        if (0 === $size) {
            return '';
        }

        $string = fread($this->stream, $this->charsize * $size);

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($string, 'UTF-8', $this->getCharset());
        }

        if (function_exists('iconv')) {
            return iconv($this->getCharset(), 'UTF-8', $string);
        }

        return $string;
    }

    /**
     * @return int
     */
    private function getInt($offset)
    {
        return $offset >> 2;
    }

    /**
     * @return array
     */
    private function getIntVector($offset)
    {
        fseek($this->stream, $offset + $this->headerSize);

        $length = $this->readInt();
        $array = array();

        for ($i = 0; $i < $length; ++$i) {
            $array[] = $this->readInt();
        }

        return $array;
    }

    /**
     * @return string
     */
    private function getBinary($offset)
    {
        fseek($this->stream, $offset + $this->headerSize);

        $length = $this->readInt();
        $binary = fread($this->stream, $length);

        return $binary;
    }

    private function getTableKey($offset)
    {
        $mark = ftell($this->stream);

        fseek($this->stream, $offset);

        $key = '';

        while("\0" !== ($char = fgetc($this->stream))) {
            $key .= $char; 
        }

        fseek($this->stream, $mark);

        return $key;
    }

    private function readInt()
    {
        return current(unpack('V', fread($this->stream, 4)));
    }

    private function readInt16()
    {
        return current(unpack('S', fread($this->stream, 2)));
    }
}