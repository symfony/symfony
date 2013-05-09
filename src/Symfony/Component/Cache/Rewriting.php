<?php

namespace Symfony\Component\Cache;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Rewriting
{
    /**
     * @var array
     */
    private $aliases = array();

    /**
     * @var array
     */
    private $patterns = array();

    /**
     * @param string $key
     * @param string $alias
     *
     * @return Rewriting
     */
    public function addAlias($key, $alias)
    {
        $this->aliases[$key] = $alias;

        return $this;
    }

    /**
     * @param string $pattern
     * @param string $replacement
     *
     * @return Rewriting
     */
    public function addPattern($pattern, $replacement)
    {
        $this->patterns[$pattern] = $replacement;

        return $this;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function rewrite($key)
    {
        if (isset($this->aliases[$key])) {
            return $this->aliases[$key];
        }

        foreach ($this->patterns as $pattern => $replacement) {
            if (preg_match($pattern, $key)) {
                return preg_replace($pattern, $replacement, $key);
            }
        }

        return $key;
    }
}
