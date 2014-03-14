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
* ActiveMQ Documentation: http://activemq.apache.org/getting-started.html

## Step by Step

### Active MQ Certificate
1. Download ActiveMQ from the official website and unpack it
2. Delete the original keystore and truststore (conf/broker.{k,t}s)
3. Create a new Keystore with a key using keytool (comes with JDK)
   1. `keytool -genkey -keyalg RSA -alias broker -keystore broker.ks -validity 365 -keysize 2048`
4. Set it in the activemq config (conf/activemq.xml). The truststore is
   configured here as well. We are going to create it with the client
   certificate though.
    ```<sslContext>
           <sslContext keyStore="file:${activemq.base}/conf/broker.ks/"
            keyStorePassword="broker"
            trustStore="file:${activemq.base}/conf/broker.ts/"
            trustStorePassword="broker" />
       </sslContext>
    ```

### PHP Client Certificate
1. Create a keypair for the client
   * `openssl genrsa -des3 -out php-client.key 2048`
2. Create a signing request
   * `openssl req -new -key php-client.key -out php-client.csr`
3. We remove the password from the client for convenience. We could have left it
   encrypted and tell PHP the password with the ssl context parameters.
   * `openssl rsa -in php-client.key -out php-client.key`
4. Self-sign the certificate signing request (the CSR is not required anymore after this step)
   * `openssl x509 -req -days 365 -in php-client.csr -signkey php-client.key -out php-client.crt`
5. Create the client chain. PHP needs a concatenanted file starting with the private key
   followed by the certificate. Note that if you have a certificate hierarchy, all intermediate
   certificates including the root CA itself must be part of this file as well.
   * `cat php-client.key php-client.crt > php-client-chain.pem`
   * Or, alternatively: `cat php-client.key php-client.crt intermediate.crt rootca.crt > php-client-chain.pem`
6. The following command imports the certificate of the php-client into the
   broker's truststore. It is perfectly fine to use an existing keystore, but
   you might want to have control over whom you trust and therefore create a new
   one. If the specified truststore does not exist, it is created.
   * `keytool -import php-client.crt -alias php-client -keystore path/to/activemq/conf/broker.ts`

