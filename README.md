RESTnag
=======

RESTnag is a RESTful interface to nagios3. You can use it to automate nagios configuration and to query nagios services status (not planned now). 
The code is written in PHP. It depends on [Silex framework](http://silex-project.org/) and on few system commands.

Requirements
------------

* Nagios3 server
* HTTP server with PHP5.3 and PEAR

Installation
------------

Install example on a Debian server:

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

Web server must have write permissions on nagios config files:

```bash
# allows web server to start/stop nagios with sudo
apt-get install sudo 
echo 'www-data   ALL = NOPASSWD: /etc/init.d/nagios3' > /etc/sudoers.d/restnag
chmod 0440 /etc/sudoers.d/restnag

# allows web server to write nagios config files
adduser www-data nagios
chgrp -R nagios /etc/nagios3/
chmod ug+rwx /etc/nagios3/
find /etc/nagios3 -type d -exec chmod g+rws {} \;
find /etc/nagios3 -type f -exec chmod ug+rw {} \;
chgrp -R nagios /var/lib/nagios3/
chmod ug+rwx /var/lib/nagios3/
find /var/lib/nagios3 -type d -exec chmod g+rws {} \;
find /var/lib/nagios3 -type f -exec chmod ug+rw {} \;
```

Usage
-----

This example assumes that your HTTP server where is installed your RESTful service is http://myserver/ and that this server is protected with login and password.

```bash
echo "-1" | curl -u login:password -d @- -X PUT http://myserver/config/nagios.cfg/debug_level/0
```
