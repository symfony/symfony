<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 6/28/15
 * Time: 1:14 PM
 */

namespace Symfony\Component\HttpFoundation\Header;


class CacheControl
{
    protected $cacheControl;

    public function __construct(array $cacheControl = array())
    {
        $this->cacheControl = $cacheControl;
    }

    public function __toString()
    {
        $parts = array();
        ksort($this->cacheControl);
        foreach ($this->cacheControl as $key => $value) {
            if (true === $value) {
                $parts[] = $key;
            } else {
                if (preg_match('#[^a-zA-Z0-9._-]#', $value)) {
                    $value = '"'.$value.'"';
                }

                $parts[] = "$key=$value";
            }
        }

        return implode(', ', $parts);
    }

    public static function fromString($header)
    {
        $cacheControl = array();
        preg_match_all('#([a-zA-Z][a-zA-Z_-]*)\s*(?:=(?:"([^"]*)"|([^ \t",;]*)))?#', $header, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $cacheControl[strtolower($match[1])] = isset($match[3]) ? $match[3] : (isset($match[2]) ? $match[2] : true);
        }

        return new static($cacheControl);
    }


    /**
     * Adds a custom Cache-Control directive.
     *
     * @param string $key The Cache-Control directive name
     * @param mixed $value The Cache-Control directive value
     * @return $this
     */
    public function addDirective($key, $value = true)
    {
        $this->cacheControl[$key] = $value;
        return $this;
    }

    /**
     * Returns true if the Cache-Control directive is defined.
     *
     * @param string $key The Cache-Control directive
     * @return bool true if the directive exists, false otherwise
     */
    public function hasDirective($key)
    {
        return array_key_exists($key, $this->cacheControl);
    }

    /**
     * Returns a Cache-Control directive value by name.
     *
     * @param string $key The directive name
     * @return mixed|null The directive value if defined, null otherwise
     */
    public function getDirective($key)
    {
        return array_key_exists($key, $this->cacheControl) ? $this->cacheControl[$key] : null;
    }

    /**
     * Removes a Cache-Control directive.
     *
     * @param string $key The Cache-Control directive
     * @return $this
     */
    public function removeDirective($key)
    {
        unset($this->cacheControl[$key]);
        return $this;
    }
}