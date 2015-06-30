<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 6/28/15
 * Time: 1:13 PM
 */

namespace Symfony\Component\HttpFoundation\Header;


interface SelfParsingHeaderInterface extends ParsedHeaderInterface
{
    /**
     * @param $header
     * @return ParsedHeaderInterface
     */
    public static function parseHeader($header);
}