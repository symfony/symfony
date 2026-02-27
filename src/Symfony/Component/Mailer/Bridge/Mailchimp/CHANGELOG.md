CHANGELOG
=========

7.4
---

* Add support for specifying a subaccount using the `X-MC-Subaccount` header

7.2
---

* Add support for webhook

4.4.0
-----

 * [BC BREAK] Renamed and moved `Symfony\Component\Mailer\Bridge\Mailchimp\Http\Api\MandrillTransport`
   to `Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillApiTransport`, `Symfony\Component\Mailer\Bridge\Mailchimp\Http\MandrillTransport`
   to `Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillHttpTransport`, `Symfony\Component\Mailer\Bridge\Mailchimp\Smtp\MandrillTransport`
   to `Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillSmtpTransport`.

4.3.0
-----

 * Added the bridge
