CHANGELOG
=========

8.2
---

 * Add `JsonLinksetSerializer` and `JsonLinksetParser` to write and read `application/linkset+json` documents (RFC 9264)
 * Add `LinkTemplateHeaderSerializer` and `LinkTemplateHeaderParser` to write and read `Link-Template` headers (RFC 9652)
 * Make `AddLinkHeaderListener` send templated links in a `Link-Template` header instead of dropping them

8.1
---

 * Add `Link::AS_*` constants for the `as` attribute of `rel=preload` / `rel=modulepreload`

7.4
---

 * Add `HttpHeaderParser` to read `Link` headers from HTTP responses
 * Make `HttpHeaderSerializer` non-final

4.4.0
-----

 * implement PSR-13 directly

3.3.0
-----

 * added the component
