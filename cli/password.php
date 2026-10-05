<?php
// Read a password from standard input without placing it in shell history.
if (PHP_SAPI !== 'cli') { exit(1); }
fwrite(STDERR, "Password via stdin: ");
$password = rtrim(fgets(STDIN), "\r\n");
if (strlen($password)<12) { fwrite(STDERR,"Use at least 12 characters\n"); exit(1); }
echo password_hash($password,PASSWORD_DEFAULT), PHP_EOL;
