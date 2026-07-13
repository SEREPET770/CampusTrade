<?php
// tagihan_saya.php
// File ini sudah tidak digunakan.
// Redirect permanen ke pesanan_saya.php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

header('Location: pesanan_saya.php');
exit;
