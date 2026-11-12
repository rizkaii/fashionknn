<?php
require_once '../app/config/database.php';
session_destroy();
header('Location: index.php');
exit;
