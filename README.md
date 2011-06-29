RESTnag
=======

RESTnag is a RESTful interface to nagios3. You can use it to automate nagios configuration and to query nagios services status (not planned now). 
The code is written in PHP. It depends on [Silex framework](http://silex-project.org/) and on few system commands.

Requirements
------------

A nagios3 server

Installation
------------

```bash
apt-get install apache2 libapache2-mod-php5 php-pear wget 
echo 'suhosin.executor.include.whitelist="phar"' > /etc/php5/apache2/conf.d/restnag.ini
a2enmod rewrite
/etc/init.d/apache2 restart
wget http://silex-project.org/get/silex.phar -O /var/www/silex.phar
pear channel-discover pear.pxxo.net
pear install pxxo/atomwriter
cd /var/www/
git init
git remote add origin git://github.com/kerphi/RESTnag.git
git pull origin master
```

Usage
-----

This example assumes that your HTTP server where is installed your RESTful service is http://myserver/ and that this server is protected with login and password.

```bash
echo "-1" | curl -u login:password -X PUT http://myserver/config/nagios.cfg/debug_level/0
```
