<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 6/28/15
 * Time: 1:19 PM
 */

namespace Symfony\Component\HttpFoundation\Header;


class IfNoneMatch
{
    private $eTags;

    public function __construct(array $eTags = array())
    {
        $this->eTags = $eTags;
    }

    public function __toString()
    {
        return implode(', ', $this->eTags);
    }

    /**
     * @param $header
     * @return IfNoneMatch
     */
    public static function fromString($header)
    {
        return new static(preg_split('/\s*,\s*/', $header, null, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * @return mixed
     */
    public function getETags()
    {
        return $this->eTags;
    }
}