php-stomp-cert-example
======================

An example project demonstrating the PHP Stomp library with SSL Certificates

The current version of the PHP Stomp library uses the function fsockopen, which
makes it impossible to send a certificate. On top of that, PHP does not verify
certificates of peers by default in versions before PHP 5.6.

This project aims to demonstrate how a modified version of the PHP Stomp library
may be used to communicate securely with a message broker. As an example,
ActiveMQ has been used, but this would really work with any broker that supports
Stomp.

By securely, I mean the client verifies the certificate of the server. And, a
client certificate is used for authentication.


## Related Linkes
* PHP TLS Peer Verification RFC: https://wiki.php.net/rfc/tls-peer-verification
* ActiveMQ Documentation: phttp://activemq.apache.org/getting-started.html
