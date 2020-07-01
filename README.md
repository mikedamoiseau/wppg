# WPPG - WP Project Generator

## What is WPPG
**WPPG** was first created as an internal tool for the company I am currently working for: [Buzzwoo](https://www.buzzwoo.de).

It is a command line tool used to create a new empty [**WordPress**](https://wordpress.org/) project running on [Docker-Compose](https://docs.docker.com/compose/). Related configuration files are also created by WPPG (readme, gitignore, ...).

## Current version
1.0 -- See [changelog](./changelog) for the versions and related changes.

## How to use it?
There is only one command at the moment and it generates a new empty WordPress project:

```
$ ./wppg new
```

The rest of the process consist in answering a few questions related to the WordPress project you want to create:
- The name of the project
- Some questions related to the WordPress user account
- Some questions related to the configuration of WordPress itself:
- The web server environment
- The database environment
- Whether to include PHPMyAdmin or not

## Files and folders created by *WPPG*
The project will be created in a specific folder based on the project name
```
|_ [project folder]
  |[_development]
    |_ [docker]
      |_ [php]
        |_ [scripts]
          |_ entrypoint.sh
        |_ php-ini-overrides.ini
      |_ vhost.conf
  |_ [html]
  |_ readme.md
  |_ .gitignore
  |_ .gitattributes
  |_ .editorconfig
```

## Tools automatically included
1. [WP-CLI](https://wp-cli.org/) will be automatically downloaded and installed in your `wpcli` docker image, as well as the `mysql-client`, a required library to import/export the database using the command line.
2. If there is no instance of WordPress installed in the folder `html`, the latest version will be automatically downloaded when starting your containers.
3. If no configuration file is found in `html/wp-config.php`, a new configuration file will be created for you.

## How to start
Once you have created your project, you can directly start working on it.

Simply start your docker container with the following command lines:
```
$ cd project-folder
$ docker-compose up
```
The container may take some time to start the first time as it will download the latest version of WordPress.

