<?php
/**
 * Beendet die Administrator‑Session und leitet zum Login zurück.
 */

session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;