<?php
// salir.php — cierra la sesión y regresa al login.
session_start();
session_destroy();
header('Location: login.php');
exit;
