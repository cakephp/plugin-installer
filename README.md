CakePHP Plugin Installer
========================

A composer installer for installing CakePHP 3.0+ plugins.

This installer ensures your application is aware of CakePHP plugins installed
by composer in `vendor/`.

Usage
-----

In your CakePHP plugin folder run:

```
composer require cakephp/plugin-installer:*
```

or add

`"cakephp/plugin-installer": "*"` to the `require` section of your
plugin's `composer.json`.

For the installer to work properly ensure that your plugin's composer config
file has a proper autoload section. Assuming your plugin's namespace is "MyPlugin"
the autoload section would be like:

```
"autoload": {
    "psr-4": {
        "MyPlugin\\": "src"
    }
}
```

Not strictly necessary for the working of the installer but ideally you would
also have an "autoload-dev" section for loading test files:

```
"autoload": {
    "psr-4": {
        "MyPlugin\\": "src"
    }
},
"autoload-dev": {
    "psr-4": {
        "MyPlugin\\Test\\": "tests",
        "Cake\\Test\\Fixture\\" : "vendor/cakephp/cakephp/test/Fixture"
    }
}
```

If your top level namespace is a vendor name then your namespace to path mapping
would be like:

```
"autoload": {
    "psr-4": {
        "MyVendor\\MyPlugin\\": "src"
    }
},
"autoload-dev": {
    "psr-4": {
        "MyVendor\\MyPlugin\\Test\\": "tests",
        "Cake\\Test\\Fixture\\" : "vendor/cakephp/cakephp/test/Fixture"
    }
}
```
