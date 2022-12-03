CHANGELOG
=========

6.1
---

* `UrlPackage` accepts empty strings as `base_url`, in order to simplify local dev configuration

6.0
---

* Remove `RemoteJsonManifestVersionStrategy`, use `JsonManifestVersionStrategy` instead

5.3
---

 * deprecated `RemoteJsonManifestVersionStrategy`, use `JsonManifestVersionStrategy` instead.

5.1.0
-----

 * added `RemoteJsonManifestVersionStrategy` to download manifest over HTTP.

4.2.0
-----

 * added different protocols to be allowed as asset base_urls

3.4.0
-----

 * added optional arguments `$basePath` and `$secure` in `RequestStackContext::__construct()`
   to provide a default request context in case the stack is empty

3.3.0
-----
 * Added `JsonManifestVersionStrategy` as a way to read final,
   versioned paths from a JSON manifest file.

2.7.0
-----

 * added the component
