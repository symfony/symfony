UPGRADE FROM 2.1 to 2.2
=======================

### HttpFoundation

 * The MongoDbSessionHandler default field names and timestamp type have changed.

   The `sess_` prefix was removed from default field names. The session ID is
   now stored in the `_id` field by default. The session date is now stored as a
   `MongoDate` instead of `MongoTimestamp`, which also makes it possible to use
   TTL collections in MongoDB 2.2+ instead of relying on the `gc()` method.

 * The Stopwatch functionality was moved from HttpKernel\Debug to its own component

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

### Form

  * The PasswordType is now not trimmed by default.
