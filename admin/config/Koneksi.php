<?php

$localserver = "localhost";
$user = "root";
$pass = "";
$db = "campus_trade";

$koneksi = mysqli_connect($localserver, $user, $pass, $db);

if (mysqli_connect_errno()) {
  echo "koneksi database gagal" . mysqli_connect_error();
}
