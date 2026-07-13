<?php

$localserver = "localhost";
$user = "root";
$pass = "";
$db = "campus_trade";

$koneksi = mysqli_connect($localserver, $user, $pass, $db);

if (mysqli_connect_errno()) {
  echo "koneksi database gagal" . mysqli_connect_error();
}

/**
 * Normalisasi nomor WhatsApp ke format internasional tanpa tanda.
 * Contoh: 081234567890 -> 6281234567890
 */
function wa_phone(?string $nomor): string
{
  if ($nomor === null) {
    return '';
  }

  $phone = preg_replace('/\D/', '', $nomor);

  if ($phone === '') {
    return '';
  }

  if (strpos($phone, '0') === 0) {
    return '62' . substr($phone, 1);
  }

  if (strpos($phone, '62') === 0) {
    return $phone;
  }

  if (strpos($phone, '8') === 0) {
    return '62' . $phone;
  }

  return $phone;
}

if (isset($_SESSION['id_user'])) {
  $id_user_aktif = $_SESSION['id_user'];
  // Update kolom terakhir_online ke waktu server saat ini
  $query_update_online = "UPDATE users SET terakhir_online = NOW() WHERE id_user = ?";
  $stmt_online = $koneksi->prepare($query_update_online);
  $stmt_online->bind_param("i", $id_user_aktif);
  $stmt_online->execute();
  $stmt_online->close();
}
