#!/usr/bin/php
<?php

include realpath(dirname(__FILE__)) . "/database.php";

include realpath(dirname(__FILE__)) . "/MC.php";
include realpath(dirname(__FILE__)) . "/../BasicObject.php";

echo "Setting up database {$db_settings['database']} for testing\n";

db_create();

echo "Dropping database if exists\n";

$db->query("DROP DATABASE `{$db_settings['database']}`");

echo "Creating database\n";

$db->query("CREATE DATABASE `{$db_settings['database']}`");

echo "Importing tables\n";

db_select_database();

db_run_file("db.sql");

echo "Clearing up in memcached\n";
BasicObject::clear_structure_cache(MC::get_instance());
echo "Done\n";

