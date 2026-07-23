Ntfy Notifier
=============

Provides [Ntfy](https://docs.ntfy.sh/) integration for Symfony Notifier.

DSN example
-----------

```
NTFY_DSN=ntfy://[USER:PASSWORD]@default[:PORT]/TOPIC?[secureHttp=[on]]
```
where:
- `URL` is the ntfy server which you are using
    - if `default` is provided, this will default to the public ntfy server hosted on [ntfy.sh](https://ntfy.sh/).
- `TOPIC` is the topic on this ntfy server.
- `PORT` is an optional specific port.
- `USER`and `PASSWORD` are username and password in case of access control supported by the server

In case of a non-secure server, you can disable https by setting `secureHttp=off`. For example if you use a local [Ntfy Docker image](https://hub.docker.com/r/binwiederhier/ntfy) during development or testing.

Sponsor
-------

This package is looking for a [backer][1].

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[3]: https://symfony.com/sponsor
