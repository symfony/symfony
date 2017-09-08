<?php

namespace Symfony\Component\HttpFoundation;

/**
 * @author Bartłomiej Krukowski <bartlomiej@krukowski.me>
 */
interface HtmlResponseInterface
{
    /**
     * @param string $html Extra html code to inject before closing body tag
     */
    public function appendToBody($html);
}
