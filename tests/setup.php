#!/usr/bin/php
<?php

include realpath(dirname(__FILE__)) . "/database.php";

echo "Setting up database {$db_settings['database']} for testing\n";

echo "Dropping database if exists\n";

$db->query("DROP DATABASE `{$db_settings['database']}` IF EXISTS");

echo "Creating database\n";

$db->query("CREATE DATABASE `{$db_settings['database']}`");

echo "Importing tables\n";

db_run_file("db.sql");

echo "Done\n";


