---
layout: page
title: Installing TeamPass on Linux
---

<p class="message">
    This page describes how to install TeamPass on a Linux server.
</p>


## Apache server

If you already have an Apache server installed with PHP and MySQL, then you can jump to next chapter. Otherwise, it is recommended to install XAMPP for Linux.
Please follow installation of XAMPP on [ApacheFriends.org](http://www.ApacheFriends.org).

## Prepare Database

* Open PhpMyAdmin
* Select tab called `Databases`
* In the `Create new database` section, enter your database name (for example `teampass`) and select `UTF8_general_ci` as collation.
* Click on `Create` button

## Get TeamPass

* Once your Apache server is running, download TeamPass.
* Unzip the file into your localhost folder (by default it is `/opt/lampp/htdocs`) using command `unzip teampass.zip -d /opt/lampp/htdocs`


## Set MySQL database Administrator

We will now create a specific Administrator to this database
* Click on `localhost` in order to get back to home page
* Select `Privileges` tab
* Click on `Add a new user` link
* Enter the login information (I suggest to create a user `teampass_admin` for better understanding of what is this user)
* Do not give any rights/privileges at this level of the user creation
* Click on `Go` button

Now it's time to set some privileges to this user.

* From Home page, click on `Privileges` tab
* Click on `Edit privileges` button corresponding to the `teampass_admin` user
* Click on `Check All` link
* Validate by clicking on button `Go`

## Set CHMOD on folders

* Open your terminal
* Point to htdocs folder 
{% highlight js %}cd /opt/lampp/htdcos){% endhighlight %}
* Enter command 
{% highlight js %}chmod -R 777 teampass{% endhighlight %}

## Install TeamPass

* Open your web browser
* Enter url `http://localhost/teampass` or your specific domain
* Follow the several steps (here bellow the 3 first steps)

20-11-2011 17-47-34	
{% lightbox thumb /assets/images/20-11-2011-17-47-34.png group:"images" caption:"test image title" alt="test image" %}

20-11-2011 17-50-10	

20-11-2011 17-50-35	

*Once installation is finished, you can use TeamPass on your Linux server.

