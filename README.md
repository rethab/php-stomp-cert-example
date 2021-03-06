Prettier version of this page: http://rethab.github.io/php-stomp-cert-example

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
5. The following command imports the certificate of the php-client into the
   broker's truststore. It is perfectly fine to use an existing keystore, but
   you might want to have control over whom you trust and therefore create a new
   one. If the specified truststore does not exist, it is created (it will thus
   ask you for a password).
   * `keytool -import -file php-client.crt -alias php-client -keystore path/to/activemq/conf/broker.ts`

### ActiveMQ Configuration
1. First of all, we need to tell ActiveMQ that we are going to authenticate users
   based on their certificate. Although topics (and queues) are created lazily,
   we want to authorize the groups to read and/or write on certain queues rather
   than just granting global write access. The following configuration snipped
   tells it to use the users.properties and groups.properties in combination
   with certificates. Place this in login.config:
    ```
    activemq-certificate {
        org.apache.activemq.jaas.TextFileCertificateLoginModule
            required
            org.apache.activemq.jaas.textfiledn.user="users.properties"
            org.apache.activemq.jaas.textfiledn.group="groups.properties";
    };
    ```
2. Next up, let us map the certificate information to a username. The following
   line ought to be placed in the file users.properties and tells ActiveMQ to
   map a request with the following certificate information to the user php-client.
   Note that not just anybody could send a certificate with this information,
   since we imported the certificate into the truststore beforehand and only that
   one will be accepted.
    `php-client=CN=PHP Test, OU=Engineering, O=Company Test, L=Location Test, ST=State Test, C=US`
3. ActiveMQ gives permissions on topics and queues to groups, which is why we
   also need to create a group for our new user. The following line adds our
   user php-client to the group php-client. Note that, if the group should have
   more than one user, separate them with a comma. gropus.properties:
    `php-client=php-client`
4. We now tell ActiveMQ to use the configuration 'activemq-certificate' (as
   defined in login.config) with the 'jaasCertificateAuthenticationPlugin' and
   then setup the authorizationMap. We basically allow our members of the group
   php-client to create the queue (admin), because we will create it when we
   send the first message, and members of the group php-client are allowed to
   write to it. Besides, we also need to grant the php-client group admin
   privileges on the advisory topics ('>' is a wildcard). Read more about
   advisory topics in the ActiveMQ manual.
    ```
    <plugins>
        <jaasCertificateAuthenticationPlugin configuration="activemq-certificate"/>
        <authorizationPlugin>
            <map>
                <authorizationMap>
                    <authorizationEntries>
                        <authorizationEntry topic="TestQueue" admin="php-client" write="php-client" />
                        <authorizationEntry topic="ActiveMQ.Advisory.>" admin="php-client" />
                    </authorizationEntries>
                </authorizationMap>
            </map>
        </authorizationPlugin>
    </plugins>
    ```
5. Finally, we configure the stomp connect in the activemq.xml accordingly.
   Suffix the URI part of the transport connector entry scheme with '+ssl' and add a
   needClientAuth to the parameters of the URI such that it now looks like this:
   `<transportConnector name="stomp" uri="stomp+ssl://0.0.0.0:61613?needClientAuth=true&amp;maximumConnections=1000&amp;wireFormat.maxFrameSize=104857600"/>`

### PHP Client Code
Now that we have both the certificate of ActiveMQ (the only one we are going to
accept as our peer) as well as a certificate we are going to use on the client
side (and ActiveMQ knows about it by means of the trust store), we can put
together the client side code to send a message to ActiveMQ from PHP.
1. PHP needs a concatenanted file starting with the private key
   followed by the certificate. Note that if you have a certificate hierarchy, all intermediate
   certificates including the root CA itself must be part of this file as well.
   * `cat php-client.key php-client.crt > php-client-chain.pem`
   * Or, alternatively (with intermediate certificates):
         `cat php-client.key php-client.crt intermediate.crt rootca.crt > php-client-chain.pem`
2. We use the certficate of the broker directly from the PHP side rather than
   using a common CA. For that, we need to export the certificate in the correct
   format from the broker's keystore (note the '-rfc' option):
   `keytool -export -rfc -alias broker.ks -keystore conf/broker.ks file broker.crt`
3. Now we can pass these two files as ssl context parameters to stomp using the
   following array:
   ```
   $opts = array(
        'ssl' => array(
            'local_cert' => './php-client-chain.pem',
            'cafile' => './broker.crt',
            'verify_peer' => true,
        )
    );
   ```
4. Finally, we can send the message to stomp:
   ```
   $con = new FuseSource\Stomp\Stomp('ssl://localhost:61613', $opts);
   $con->connect();

   $body = json_encode(array('message' => array('content' => 'hello, world')));
   $headers = array('persistent' => 'true');
   var_dump($con->send('/topic/TestQueue', $body, $headers));
   ```

## Debugging
- Connect to the server and get the server certificate chain as well as the
  potentially accepted certificates from a client. You should see the
  certificate your previously put into the truststore:
  `openssl s_client -connect localhost:61613 -cert php-client-chain.pem -debug -showcerts`
- Is the certficiate in the correct format? Try to parse it with the following
  two functions and look at the output (with, e.g. var_dump):
  `openssl_x509_parse(file_get_contents('./file.crt'))`
