<?php
// seller/ajax_update_produk.php
// AJAX endpoint untuk edit inline produk penjual.
// FIX: kolom yang benar adalah penulis_penerbit, bukan penulis.

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

// =========================
// VALIDASI AKSES
// =========================
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['id_user'])) {
  echo json_encode(['status' => 'error', 'message' => 'Akses ditolak atau sesi habis.']);
  exit;
}

require_once '../config/Koneksi.php';
/** @var mysqli $koneksi */

$id_user   = (int) $_SESSION['id_user'];
$id_produk = isset($_POST['id_produk']) ? (int) $_POST['id_produk'] : 0;

if ($id_produk <= 0) {
  echo json_encode(['status' => 'error', 'message' => 'ID produk tidak valid.']);
  exit;
}

// =========================
// AMBIL & VALIDASI INPUT
// =========================
$nama_produk     = trim($_POST['nama_produk']     ?? '');
$harga           = (int) ($_POST['harga']         ?? 0);
$brand           = trim($_POST['brand']           ?? '');
$penulis_penerbit = trim($_POST['penulis']        ?? ''); // field name 'penulis' dari JS, disimpan ke kolom penulis_penerbit
$kelebihan       = trim($_POST['kelebihan']       ?? '');
$kekurangan      = trim($_POST['kekurangan']      ?? '');
$deskripsi       = trim($_POST['deskripsi']       ?? '');
$id_lokasi = isset($_POST['id_lokasi']) && $_POST['id_lokasi'] !== ''
  ? (int) $_POST['id_lokasi']
  : null;

if (empty($nama_produk)) {
  echo json_encode(['status' => 'error', 'message' => 'Nama produk tidak boleh kosong.']);
  exit;
}

if ($harga <= 0) {
  echo json_encode(['status' => 'error', 'message' => 'Harga harus lebih dari 0.']);
  exit;
}

// =========================
// PASTIKAN PRODUK MILIK USER
// =========================
$cek = $koneksi->prepare("SELECT id_produk, status_produk FROM produk WHERE id_produk = ? AND id_user = ?");
$cek->bind_param('ii', $id_produk, $id_user);
$cek->execute();
$produk_cek = $cek->get_result()->fetch_assoc();
$cek->close();

if (!$produk_cek) {
  echo json_encode(['status' => 'error', 'message' => 'Produk tidak ditemukan atau bukan milik Anda.']);
  exit;
}

if ($produk_cek['status_produk'] === 'terjual') {
  echo json_encode(['status' => 'error', 'message' => 'Produk yang sudah terjual tidak dapat diubah.']);
  exit;
}

// keterangan produk
$stmt = $koneksi->prepare("
    UPDATE produk SET
        nama_produk      = ?,
        harga            = ?,
        brand            = ?,
        penulis_penerbit = ?,
        kelebihan        = ?,
        kekurangan       = ?,
        deskripsi        = ?,
        id_lokasi        = ?
    WHERE id_produk = ?
      AND id_user   = ?
");

$stmt->bind_param(
  'sisssssiii',
  $nama_produk,
  $harga,
  $brand,
  $penulis_penerbit,
  $kelebihan,
  $kekurangan,
  $deskripsi,
  $id_lokasi,
  $id_produk,
  $id_user
);

$stmt->bind_param(
  'sisssssii',
  $nama_produk,
  $harga,
  $brand,
  $penulis_penerbit,
  $kelebihan,
  $kekurangan,
  $deskripsi,
  $id_produk,
  $id_user
);

if ($stmt->execute()) {
  echo json_encode(['status' => 'success', 'message' => 'Produk berhasil diperbarui.']);
} else {
  echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . $koneksi->error]);
}

$stmt->close();
