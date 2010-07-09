<?php

namespace Symfony\Components\OutputEscaper;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Abstract class that provides an interface for escaping of output.
 *
 * @package    Symfony
 * @subpackage Components_OutputEscaper
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Mike Squire <mike@somosis.co.uk>
 */
abstract class Escaper
{
    /**
     * The value that is to be escaped.
     *
     * @var mixed
     */
    protected $value;

    /**
     * The escaper (a PHP callable) that is going to be applied to the value and its
     * children.
     *
     * @var string
     */
    protected $escaper;

    static protected $charset = 'UTF-8';
    static protected $safeClasses = array();
    static protected $escapers;

    /**
     * Constructor.
     *
     * Since Escaper is an abstract class, instances cannot be created
     * directly but the constructor will be inherited by sub-classes.
     *
     * @param string $callable A PHP callable
     * @param string $value    Escaping value
     */
    public function __construct($escaper, $value)
    {
        if (null === self::$escapers) {
            self::initializeEscapers();
        }

        $this->escaper = is_string($escaper) && isset(self::$escapers[$escaper]) ? self::$escapers[$escaper] : $escaper;
        $this->value = $value;
    }

    /**
     * Decorates a PHP variable with something that will escape any data obtained
     * from it.
     *
     * The following cases are dealt with:
     *
     *    - The value is null or false: null or false is returned.
     *    - The value is scalar: the result of applying the escaping method is
     *      returned.
     *    - The value is an array or an object that implements the ArrayAccess
     *      interface: the array is decorated such that accesses to elements yield
     *      an escaped value.
     *    - The value implements the Traversable interface (either an Iterator, an
     *      IteratorAggregate or an internal PHP class that implements
     *      Traversable): decorated much like the array.
     *    - The value is another type of object: decorated such that the result of
     *      method calls is escaped.
     *
     * The escaping method is actually a PHP callable. This class hosts a set
     * of standard escaping strategies.
     *
     * @param  string $escaper The escaping method (a PHP callable) to apply to the value
     * @param  mixed  $value   The value to escape
     *
     * @return mixed Escaped value
     *
     * @throws \InvalidArgumentException If the escaping fails
     */
    static public function escape($escaper, $value)
    {
        if (null === $value) {
            return $value;
        }

        if (null === self::$escapers) {
            self::initializeEscapers();
        }

        if (is_string($escaper) && isset(self::$escapers[$escaper])) {
            $escaper = self::$escapers[$escaper];
        }

        // Scalars are anything other than arrays, objects and resources.
        if (is_scalar($value)) {
            return call_user_func($escaper, $value);
        }

        if (is_array($value)) {
            return new ArrayDecorator($escaper, $value);
        }

        if (is_object($value)) {
            if ($value instanceof Escaper) {
                // avoid double decoration
                $copy = clone $value;

                $copy->escaper = $escaper;

                return $copy;
            }

            if (self::isClassMarkedAsSafe(get_class($value))) {
                // the class or one of its children is marked as safe
                // return the unescaped object
                return $value;
            }

            if ($value instanceof SafeDecorator) {
                // do not escape objects marked as safe
                // return the original object
                return $value->getValue();
            }

            if ($value instanceof \Traversable) {
                return new IteratorDecorator($escaper, $value);
            }

            return new ObjectDecorator($escaper, $value);

        }

        // it must be a resource; cannot escape that.
        throw new \InvalidArgumentException(sprintf('Unable to escape value "%s".', var_export($value, true)));
    }

    /**
     * Unescapes a value that has been escaped previously with the escape() method.
     *
     * @param  mixed $value The value to unescape
     *
     * @return mixed Unescaped value
     *
     * @throws \InvalidArgumentException If the escaping fails
     */
    static public function unescape($value)
    {
        if (null === $value || is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return html_entity_decode($value, ENT_QUOTES, self::$charset);
        }

        if (is_array($value)) {
            foreach ($value as $name => $v) {
                $value[$name] = self::unescape($v);
            }

            return $value;
        }

        if (is_object($value)) {
            return $value instanceof Escaper ? $value->getRawValue() : $value;
        }

        return $value;
    }

    /**
     * Returns true if the class if marked as safe.
     *
     * @param  string  $class  A class name
     *
     * @return bool true if the class if safe, false otherwise
     */
    static public function isClassMarkedAsSafe($class)
    {
        if (in_array($class, self::$safeClasses)) {
            return true;
        }

        foreach (self::$safeClasses as $safeClass) {
            if (is_subclass_of($class, $safeClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Marks an array of classes (and all its children) as being safe for output.
     *
     * @param array $classes  An array of class names
     */
    static public function markClassesAsSafe(array $classes)
    {
        self::$safeClasses = array_unique(array_merge(self::$safeClasses, $classes));
    }

    /**
     * Marks a class (and all its children) as being safe for output.
     *
     * @param string $class  A class name
     */
    static public function markClassAsSafe($class)
    {
        self::markClassesAsSafe(array($class));
    }

    /**
     * Returns the raw value associated with this instance.
     *
     * Concrete instances of Escaper classes decorate a value which is
     * stored by the constructor. This returns that original, unescaped, value.
     *
     * @return mixed The original value used to construct the decorator
     */
    public function getRawValue()
    {
        return $this->value;
    }

    /**
     * Sets the current charset.
     *
     * @param string $charset The current charset
     */
    static public function setCharset($charset)
    {
        self::$charset = $charset;
    }

    /**
     * Gets the current charset.
     *
     * @return string The current charset
     */
    static public function getCharset()
    {
        return self::$charset;
    }

    /**
     * Adds a named escaper.
     *
     * @param string $name    The escaper name
     * @param mixed  $escaper A PHP callable
     */
    static public function setEscaper($name, $escaper)
    {
        self::$escapers[$name] = $escaper;
    }

    /**
     * Initializes the built-in escapers.
     *
     * Each function specifies a way for applying a transformation to a string
     * passed to it. The purpose is for the string to be "escaped" so it is
     * suitable for the format it is being displayed in.
     *
     * For example, the string: "It's required that you enter a username & password.\n"
     * If this were to be displayed as HTML it would be sensible to turn the
     * ampersand into '&amp;' and the apostrophe into '&aps;'. However if it were
     * going to be used as a string in JavaScript to be displayed in an alert box
     * it would be right to leave the string as-is, but c-escape the apostrophe and
     * the new line.
     *
     * For each function there is a define to avoid problems with strings being
     * incorrectly specified.
     */
    static function initializeEscapers()
    {
        self::$escapers = array(
            'htmlspecialchars' =>
                /**
                 * Runs the PHP function htmlspecialchars on the value passed.
                 *
                 * @param string $value the value to escape
                 *
                 * @return string the escaped value
                 */
                function ($value)
                {
                    // Numbers and boolean values get turned into strings which can cause problems
                    // with type comparisons (e.g. === or is_int() etc).
                    return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, Escaper::getCharset()) : $value;
                },

            'entities' =>
                /**
                 * Runs the PHP function htmlentities on the value passed.
                 *
                 * @param string $value the value to escape
                 * @return string the escaped value
                 */
                function ($value)
                {
                    // Numbers and boolean values get turned into strings which can cause problems
                    // with type comparisons (e.g. === or is_int() etc).
                    return is_string($value) ? htmlentities($value, ENT_QUOTES, Escaper::getCharset()) : $value;
                },

            'raw' =>
                /**
                 * An identity function that merely returns that which it is given, the purpose
                 * being to be able to specify that the value is not to be escaped in any way.
                 *
                 * @param string $value the value to escape
                 * @return string the escaped value
                 */
                function ($value)
                {
                    return $value;
                },

            'js' =>
                /**
                 * A function that c-escapes a string after applying (cf. entities). The
                 * assumption is that the value will be used to generate dynamic HTML in some
                 * way and the safest way to prevent mishap is to assume the value should have
                 * HTML entities set properly.
                 *
                 * The (cf. js_no_entities) method should be used to escape a string
                 * that is ultimately not going to end up as text in an HTML document.
                 *
                 * @param string $value the value to escape
                 * @return string the escaped value
                 */
                function ($value)
                {
                    return str_replace(array("\\"  , "\n"  , "\r" , "\""  , "'"  ), array("\\\\", "\\n" , "\\r", "\\\"", "\\'"), (is_string($value) ? htmlentities($value, ENT_QUOTES, Escaper::getCharset()) : $value));
                },

            'js_no_entities' =>
                /**
                 * A function the c-escapes a string, making it suitable to be placed in a
                 * JavaScript string.
                 *
                 * @param string $value the value to escape
                 * @return string the escaped value
                 */
                function ($value)
                {
                    return str_replace(array("\\"  , "\n"  , "\r" , "\""  , "'"  ), array("\\\\", "\\n" , "\\r", "\\\"", "\\'"), $value);
                },
        );
    }
}
