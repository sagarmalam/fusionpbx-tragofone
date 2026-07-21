<?php

// FusionPBX loads application menu definitions from app_menu.php.
$y = 0;
$apps[$x]['menu'][$y]['title']['en-us'] = 'Tragofone Integration';
$apps[$x]['menu'][$y]['title']['en-gb'] = 'Tragofone Integration';
$apps[$x]['menu'][$y]['uuid'] = '1b9e9c69-7d33-4d44-99ae-ccecb9e5d002';
$apps[$x]['menu'][$y]['parent_uuid'] = '594d99c5-6128-9c88-ca35-4b33392cec0f';
$apps[$x]['menu'][$y]['category'] = 'internal';
$apps[$x]['menu'][$y]['icon'] = 'fa-solid fa-mobile-screen-button';
$apps[$x]['menu'][$y]['path'] = '/app/tragofone/index.php';
$apps[$x]['menu'][$y]['order'] = '90';
$apps[$x]['menu'][$y]['groups'][] = 'superadmin';
$apps[$x]['menu'][$y]['groups'][] = 'admin';
$y++;
