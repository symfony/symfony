<?php

namespace Symfony\Component\HttpKernel\HttpCache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface RendererInterface {
    public function hasSurrogateCapability(Request $request);
    public function renderIncludeTag ($uri, $alt = null, $ignoreErrors = true, $comment = '');
    public function addSurrogateControl (Response $response);
}
