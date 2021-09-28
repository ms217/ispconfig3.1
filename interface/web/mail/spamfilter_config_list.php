<?php
require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

/******************************************
* Begin Form configuration
******************************************/

$list_def_file = "list/spamfilter_config.list.php";

/******************************************
* End Form configuration
******************************************/

//* Check permissions for module
$app->auth->check_module_permissions('mail');

$app->uses('listform_actions');
//$app->listform_actions->SQLExtWhere = "wb = 'W'";

$app->listform_actions->onLoad();


?>
