<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/3/15
 * Time: 2:12 PM
 */

namespace Symfony\Component\HttpFoundation\Request\Uri;


use Symfony\Component\HttpFoundation\Request;

class UriResolver implements UriResolverInterface
{
    private $resolver;

    public function __construct(UriResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    public static function create()
    {
        $resolver = new ChainResolver();
        $resolver
            ->add(new ApacheRequestUriResolver())
            ->add(new IISWithMicrosoftRewriteModuleUriResolver())
            ->add(new IISWithASAPIRewriteUriResolver())
            ->add(new IIS7WithUrlRewriteUriResolver())
            ->add(new RequestUriUriResolver())
            ->add(new OrigPathInfoUriResolver())
        ;
        return new static($resolver);
    }

    public function resolveUri(Request $request)
    {
        $requestUri = (string) $this->resolver->resolveUri($request);
        $request->server->remove('UNENCODED_URL');
        $request->server->remove('IIS_WasUrlRewritten');
        $request->headers->remove('X_REWRITE_URL');
        $request->headers->remove('X_ORIGINAL_URL');
        $request->server->remove('HTTP_X_ORIGINAL_URL');
        $request->server->remove('ORIG_PATH_INFO');
        // normalize the request URI to ease creating sub-requests from this request
        $request->server->set('REQUEST_URI', $requestUri);
        return $requestUri;
    }
}