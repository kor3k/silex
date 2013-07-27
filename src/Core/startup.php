<?php
//TODO smazat a používat core util datetime
date_default_timezone_set( "Europe/Prague" );
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');


//http://cz.php.net/manual/en/mbstring.overload.php
ini_set( 'mbstring.func_overload' , 3 );
//nastavit pokud možno v php.ini

//$loader	=   new Composer\Autoload\ClassLoader();
//$loader->set( 'Core' , __DIR__.'/..' ); //src
//$loader->set( 'App' , __DIR__.'/..' );
//$loader->register(true);