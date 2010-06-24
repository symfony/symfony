<?php

namespace Symfony\Components\Validator\MessageInterpolator;

interface MessageInterpolatorInterface
{
    /**
     * Interpolates a text and inserts the given parameters
     *
     * @param  string $text        The text to interpolate
     * @param  array  $parameters  The parameters to insert into the text
     * @return string              The interpolated text
     */
    function interpolate($text, array $parameters = array());
}