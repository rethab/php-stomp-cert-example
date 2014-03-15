#!/usr/bin/env bash

apt-get -y install php5-cli # to run PHP programs
apt-get -y install openjdk-6-jdk # contains keytool 

# Download ActiveMQ
wget -quiet http://mirror.switch.ch/mirror/apache/dist/activemq/apache-activemq/5.9.0/apache-activemq-5.9.0-bin.tar.gz
tar xf apache-activemq-5.9.0-bin.tar.gz

# Delete config dir to use ours
rm -rf apache-activemq-5.9.0/conf
ln -s /vagrant/conf apache-activemq-5.9.0/conf

# sed -i '/display_errors = Off/c display_errors = On' /etc/php5/cli/php.ini
