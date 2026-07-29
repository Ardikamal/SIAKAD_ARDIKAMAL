<?php
define('SIAKAD_APP', true);
require __DIR__ . '/_config/constants.php';
require __DIR__ . '/_config/auth.php';

siakad_logout();
redirect('/api/login.php');
