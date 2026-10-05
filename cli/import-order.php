<?php
// Import a private JSON request without placing contact data in tracked files or logs.
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
try{$input=json_decode(file_get_contents($argv[1]??'php://stdin'),true,16,JSON_THROW_ON_ERROR);if(!is_array($input))throw new InvalidArgumentException('Invalid request');$id=(new Orders(database(configuration())))->create($input);echo 'Order saved: '.$id.PHP_EOL;}catch(Throwable $e){fwrite(STDERR,"Order import failed: check input and database\n");exit(1);}
