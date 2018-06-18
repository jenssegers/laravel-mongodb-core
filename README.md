Laravel MongoDB Core
====================

The MongoDB Core package that powers [laravel-mongodb](https://github.com/jenssegers/laravel-mongodb).

This package provides core functionality to your Laravel application to connect to a Mongo database. It provides a Mongo database connector and a query builder. If you want to have MongoDB support for your Eloquent models, check out [laravel-mongodb](https://github.com/jenssegers/laravel-mongodb).

Contributing
------------

This package is still under heavy development. I'm starting a complete rewrite of [laravel-mongodb](https://github.com/jenssegers/laravel-mongodb), starting with splitting of the core functionality into a separate package.

Laravel has changed a lot since the original code was written. I have found a much more elegant way of extending the Laravel query builder with MongoDB support using grammars.

I am currently looking for contributors and reviewers to get this package ready for production so that it can be integrated in [laravel-mongodb](https://github.com/jenssegers/laravel-mongodb).

### How can I contribute?

#### 1. Reviewing

Review code being pushed to this repository, and create issues to discuss if you may have found a better way to solve a certain functionality.

#### 2. Writing Tests

Tests are important to make sure this package remains stable during its course of development. If you want a new feature, or think something is not working like it should, please add a test proving the correct functionality, so that me or others can provide the correct implementation.

#### 3. Pull Requests

Pull requests are more than welcome to speed up the development of the package. Currently, there are quite some methods in [src/Query/Grammars/MongoGrammar.php](https://github.com/jenssegers/laravel-mongodb-core/blob/master/src/Query/Grammars/MongoGrammar.php) that throw a `not yet implemented` exception. I think implementing these are a great easy way to contribute!

#### 4. Documentation

This package provides quite some functionality, and documenting it will be a challenge. Contributions to add and/or improve documentation are certainly welcome!

Installation
------------

Make sure you have the MongoDB PHP driver installed. You can find installation instructions [here](http://php.net/manual/en/mongodb.installation.php).

Install the package using composer:

```
composer require jenssegers/mongodb-core
```

Testing
-------

The tests can be run inside a [Docker](https://www.docker.com/community-edition#/download) container using:

```
make test
```
