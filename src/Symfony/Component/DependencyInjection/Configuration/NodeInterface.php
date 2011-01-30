<?php

namespace Symfony\Component\DependencyInjection\Configuration;

interface NodeInterface
{
    function getName();
    function getPath();
    function normalize($value);
}