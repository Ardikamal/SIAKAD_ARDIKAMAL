<?php
define('SIAKAD_APP', true);
require __DIR__ . '/_config/constants.php';
require __DIR__ . '/_config/auth.php';

$user = siakad_current_user();
if ($user === null) {
    redirect('/api/login.php');
}
redirect(siakad_dashboard_url($user['role']));
