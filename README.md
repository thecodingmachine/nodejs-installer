[![Latest Stable Version](https://poser.pugx.org/mouf/nodejs-installer/v/stable.svg)](https://packagist.org/packages/mouf/nodejs-installer)
[![Latest Unstable Version](https://poser.pugx.org/mouf/nodejs-installer/v/unstable.svg)](https://packagist.org/packages/mouf/nodejs-installer)
[![License](https://poser.pugx.org/mouf/nodejs-installer/license.svg)](https://packagist.org/packages/mouf/nodejs-installer)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/thecodingmachine/nodejs-installer/badges/quality-score.png?b=1.0)](https://scrutinizer-ci.com/g/thecodingmachine/nodejs-installer/?branch=1.0)

NodeJS installer for Composer
=============================

This is an installer that will download NodeJS and NPM and install them in your Composer dependencies.
Installation is skipped if NodeJS is already available on your machine.

Why?
----

NodeJS is increasingly becoming a part of the tool-chain of modern web developers. Tools like Bower, Grunt, Gulp... are
used everyday to build applications. For the PHP developer, this means PHP projects have build dependencies on NodeJS
or Bower / NPM packages. The NodeJS-installer attempts to bridge the gap between NodeJS and PHP by making NodeJS easily 
installable as a Composer dependency.

Building on this package, other packages like [koala-framework/composer-extra-assets](https://github.com/koala-framework/composer-extra-assets)
can be used to automatically fetch Bower / NPM packages, run Gulp / Grunt tasks, etc...

How does it work?
-----------------

Simply include this package in your `composer.json` requirements:

```json
{
    "require": {
        "mouf/nodejs-installer": "~1.0"
    }
}
```

By default, if NodeJS is not available on your computer, it will be downloaded and installed in *vendor/nodejs/nodejs*.

You should access NodeJS and NPM using the scripts created into the *vendor/bin* directory:

- *vendor/bin/node* (*vendor/bin/node.bat* on Windows)
- *vendor/bin/npm* (*vendor/bin/npm.bat* on Windows)

Options
-------

A number of options are available to customize NodeJS installation:


```json
{
    "require": {
        "mouf/nodejs-installer": "~1.0"
    },
    "extra": {
        "mouf": {
            "nodejs": {
                "version": "~0.12",
                "targetDir": "vendor/nodejs/nodejs",
                "forceLocal": false
            },
            "request_options": {
                "http": {
                    "proxy": "http:http://example.webproxy.org"
                }
            }
        }
    }
}
```

Available options:

- **version**: This is the version number of NodeJS that will be downloaded and installed.
  You can specify version constraints in the usual Composer format (for instance "~0.12" or ">0.11").  
  _Default value: *_ The latest stable version of NodeJS is installed by default.
- **targetDir**: The target directory NodeJS will be installed in. Relative to project root.  
  This option is only available in the root package.  
  *Default value: vendor/nodejs/nodejs*
- **forceLocal** (boolean): If set to true, NodeJS will always be downloaded and installed locally, even if NodeJS
  is already available on your computer.  
  This option is only available in the root package.  
  *Default value: false*
- **includeBinInPath** (boolean): After the plugin is run in Composer, the *vendor/bin* directory can optionally be 
  added to the PATH. This is useful if other plugins rely on "node" or "npm" being available globally on the 
  computer. Using this option, these other plugins will automatically find the node/npm version that has been 
  downloaded. Please note that the PATH is only set for the duration of the Composer script. Your global environment
  is not impacted by this option.  
  This option is only available in the root package.  
  *Default value: false*
- **request_options** (array): Build context request options array. @ref https://www.php.net/manual/en/context.php

Custom script
-------------

The installer listens to the following composer scripts to be launched:
```
{
    "post-install-cmd": {
        // ...
    },
    "post-update-cmd": {
        // ...
    }
}
```

If you need to launch the installer manually, you can run the following command:
```
$ composer run-script download-nodejs
```
