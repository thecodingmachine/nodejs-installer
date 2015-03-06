NodeJS installer
================

This is a simple installer that let's you create simple Composer packages that are actually downloading and extracting an archive from the web.

Downloading an archive from the web is actually already possible in Composer using the ["package" repository](http://getcomposer.org/doc/05-repositories.md#package-2), but this approach has a number of drawbacks. For instance, you cannot unpack the package in the root directory, or you cannot build dependencies easily upon that package.

Using the archive installer, you can let Composer install big packages that have no Composer package for you. For instance, you can build a Drupal installer just by writing a composer.json file.

A package implementing the archive installer should contain at least these statements in *composer.json*:


	{
		...
		"type": "archive-package",
		...
		"extra": {
			"url": "http://exemple.com/myarchive.zip"
			"target-dir": "destination/directory",
			"omit-first-directory": "true|false"
		}
	}

Please note that *target-dir* is relative to the root of your project (the directory containing the *composer.json* file).
If *target-dir* is omitted, we default to the package's directory.


The *omit-first-directory* is useful if you download an archive where all the files are contained in one big directory. If you want the files without the container directory, just pass *true* to the *omit-first-directory* parameter (it defaults to false).

Detailed behaviour
------------------

The archive installer is not a perfect implementation. Actually, it is kind of stupid. Here is what you might want to know:

It assumes that the downloaded file at the URL you pass will never change. Once a download and installation is performed, it will not download the file again, unless the URL changes.
If the URL changes, it will download the new archive and overwrite any previous files.

If you uninstall the package, the downloaded files will not be removed (it is up to you to do the cleanup).

Working in team
---------------

You might wonder whether you should commit the downloaded files in your code repository or not.

Actually, it's up to you. You might want to let the other users run *composer install* to download the package, or you might as well commit the files.
If you commit the files, we strongly suggest that you also commit the *download-status.txt* file too, that you can find at the root of your package. This way, when your team-mates will run *composer install*, the package will not be downloaded again. Of course, the opposite is equally true: if you do not commit the downloaded package, then you should not commit *download-status.txt*.
