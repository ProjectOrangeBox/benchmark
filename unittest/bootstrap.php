<?php

// the project root path
define('__ROOT__', realpath(__DIR__ . '/../../../../'));
// the htdocs path
define('__WWW__', realpath(__DIR__ . '/../../../../htdocs'));

// this package depends on nothing but PHP itself, so the autoloader is all the
// bootstrap it needs
require __DIR__ . '/../../../autoload.php';
