<?php
session_start();

if (isset($_SESSION['id_user'])) {
  header("Location: ../auth/login.php");
  exit();
}
if ($_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}
