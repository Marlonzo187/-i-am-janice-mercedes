<?php
// Session beenden und ausloggen
session_start();
session_unset();
session_destroy();
header('Location: index.php');
exit;
