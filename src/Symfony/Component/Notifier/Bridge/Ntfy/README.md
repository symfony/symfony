Ntfy Notifier
=============

Provides [Ntfy](https://docs.ntfy.sh/) integration for Symfony Notifier.

DSN example
-----------
```
NTFY_DSN=ntfy://URL[:PORT]/TOPIC?[secureHttp=[on]]
```
or with username and password authorization:
```
NTFY_DSN=ntfy://USER:PASSWORD@URL[:PORT]/TOPIC?[secureHttp=[on]]
```
or with token authorization:
```
NTFY_DSN=ntfy://:TOKEN@URL[:PORT]/TOPIC?[secureHttp=[on]]
```
where:
- `URL` is the ntfy server which you are using
    - if value `default` is provided, this will default to the public ntfy server hosted on [ntfy.sh](https://ntfy.sh/).
- `TOPIC` is the topic on this ntfy server.
- `PORT` is an optional specific port.
- `USER`and `PASSWORD` are username and password in case of access control supported by the server
- `TOKEN` is token value provided by server for user

In case of a non-secure server, you can disable https by setting `secureHttp=off`. For example if you use a local [Ntfy Docker image](https://hub.docker.com/r/binwiederhier/ntfy) during development or testing.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
