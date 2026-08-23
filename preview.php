<?php
require 'config.php';
header('Content-Type: text/html; charset=utf-8');
$md = new SimpleMD();
echo $md->text($_POST['content'] ?? '');
