---
layout: page
title: Teampass installation
---

<p class="message">
    This page describes how to upgrade TeamPass.
</p>

If you have installed a previous of TeamPass, you can easily upgrade to newest version by doing as described below.

## Instruction to follow

* Put Teampass in maintenance mode,
* Make a dump of your database,
* Download lastest package,
* Unzip package into a temporary folder (let's call it [NewTP]),
* Rename the actual TeamPass folder (let's call it [OldTP])
* Upload [NewTP] on your server,
* Copy existing files in folder 'Backups', 'Files' and 'Upload' from [OldTP], and paste them in [NewTP],
* Copy the setings.php in /includes/ from [OldTP] into [NewTP],
* Enter url: http://your_domain/teampass/install/upgrade.php,
* Follow instructions,
* You can now connect to http://your_domain/teampass,
* Think about cleaning up your Browser's cache

