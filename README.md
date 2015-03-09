NodeJS installer for Composer
=============================

This is an installer that will download NodeJS and NPM install them in your Composer dependencies.
Installation is skipped if NodeJS is already available on your machine.

How does it work?
-----------------

Simply include this package in your *composer.json* requirements:

```json
{
    "require": {
        "mouf/nodejs-installer": "~1.0",
    }
}
```

By default, if NodeJS is not available on your computer, it will be downloaded and installed in *vendor/nodejs/nodejs*.

You should access NodeJS and NPM using the scripts created into the *vendor/bin* directory:

- *vendor/bin/node* (*vendor/bin/node.bat* in Windows)
- *vendor/bin/npm* (*vendor/bin/npm.bat* in Windows)

Options
-------

A number of options are available to customize NodeJS installation:


```json
{
    "require": {
        "mouf/nodejs-installer": "~1.0",
    },
    "extra": {
    	"mouf": {
    		"nodejs": {
    			"version": "0.12.0",
                "minimumVersion": "0.8.0",
                "targetDir": "vendor/nodejs/nodejs"
    		}
    	}
    }
}
```

Available options:

- **version**: This is the **exact** version number of the NodeJS version that will be downloaded and installed.
  In the current version, you *cannot* specify version ranges ("~0.12" or ">0.11" is *unsupported*).  
  *Default value: 0.12.0* 
- **minimumVersion**: Before downloading NodeJS, the installer will check the availability of NodeJS globally.
  If a NodeJS is available globally, the *minimumVersion* parameter is the minimum version NodeJS should have.
  If this condition is not met, a local install is performed.  
  *Default value: 0.8.0*
- **targetDir**: The target directory NodeJS will be installed in. Relative to project root.  
  *Default value: vendor/nodejs/nodejs*

**Note**: in the current implementation, options are only read from the "root" package.
