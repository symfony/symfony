UPGRADE FROM 2.1 to 2.2
=======================

#### Deprecations

 * The `Request::splitHttpAcceptHeader()` is deprecated and will be removed in 2.3.

   You should now use the `AcceptHeader` class which give you fluent methods to
   parse request accept-* headers. Some examples:

   ```
   $accept = AcceptHeader::fromString($request->headers->get('Accept'));
   if ($accept->has('text/html') {
       $item = $accept->get('html');
       $charset = $item->getAttribute('charset', 'utf-8');
       $quality = $item->getQuality();
   }

   // accepts items are sorted by descending quality
   $accepts = AcceptHeader::fromString($request->headers->get('Accept'))->all();

   ```
