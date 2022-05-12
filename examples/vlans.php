#!/usr/bin/env php
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once('./fake-lib/exceptions.php');
require_once('../src/easysmart.php');

$host = '192.168.0.1';
$username = 'admin';
$password = 'admin';
if( isset($_SERVER['argv'][1]) ) $host = $_SERVER['argv'][1];
if( isset($_SERVER['argv'][2]) ) $username = $_SERVER['argv'][2];
if( isset($_SERVER['argv'][3]) ) $password = $_SERVER['argv'][3];
var_dump( TpLinkEasySmart::create($host, $username, $password)->vlans() );
echo "\r\n";

