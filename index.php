<?php
/* 
Plugin Name: Crime Incident API
Description: Pull JSON data from API exposed crime incident database.
Version: 2021.01.28
*/

foreach ( \glob( __DIR__ . '/lib/*.php' ) as $file ) include $file;