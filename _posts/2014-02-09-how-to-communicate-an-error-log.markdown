---
layout: post
title:  "How to communicate an Error log?"
date:   2014-02-09 14:57:54
categories: user_guide
---

When you open an issue in Github, it could be good that you join the error generated on your server. If you don't know how to perform that, here is some tips you could follow.
# Use Firebug
<strong>Firebug</strong> is an extension for at least web browsers Chrome and Firefox. You can securely use it and only activate it when you want to report issues you have discovered using Teampass.
<!-- more -->
Get <b>[Firebug][getfirebug]</b>!

Once installed, you will get a small "bug" in the menu bar of the browser.
<p>
![Firebug disabled]({{ site.url }}/assets/2014-02-09-01.png)
</p>
</p>
![Firebug enabled]({{ site.url }}/assets/2014-02-09-02.png)
</p>
<h3>Activate options</h3>
At the very begining, you should check that next options are activated in Firebug.
<ul>
	<li>Console should be activated</li>
	<li>Script should be activated</li>
</ul>
<h3>How to get the error message</h3>
When you are facing an issue using Teampass, you should:
<ol>
	<li>Enable Firebug</li>
	<li>It should normally open the "Firebug dialogbox" (by default in the low part of your browser)</li>
	<li>Relaunch the action that causes the issue</li>
	<li>Open tab "Console"</li>
	<li>Identify the POST that has failed (it should in RED)</li>
	<li>Expand using the symbol " + "</li>
	<li>Select tab "Response"</li>
	<li>Make "Copy response body" using right-click mouse</li>
	<li>Paste this in the github message</li>
	<li>Make "Copy location with parameters" using right-click mouse</li>
	<li>Paste this in the github message</li>
</ol>
Don't forget to disable Firebug after this ;-)

[getfirebug]: http://getfirebug.com/
