<?php
// proses_checkout.php
// Memproses checkout: validasi, simpan transaksi & pengiriman, update produk.

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../config/Koneksi.php';
// Variabel koneksi: $koneksi

// ─────────────────────────────────────────
// HELPER: Redirect dengan pesan error
// ─────────────────────────────────────────
function redirect_error(string $url, string $pesan): never
{
  $_SESSION['error_checkout'] = $pesan;
  header('Location: ' . $url);
  exit;
}

// ─────────────────────────────────────────
// 1. VALIDASI METODE REQUEST
// ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../index.php');
  exit;
}

// ─────────────────────────────────────────
// 2. VALIDASI LOGIN
// ─────────────────────────────────────────
if (empty($_SESSION['id_user'])) {
  header('Location: ../auth/login.php');
  exit;
}

$id_pembeli = (int) $_SESSION['id_user'];

// ─────────────────────────────────────────
// 3. AMBIL & SANITASI INPUT FORM
// ─────────────────────────────────────────
$id_produk         = isset($_POST['id_produk'])         ? (int)   $_POST['id_produk']         : 0;
$nama_penerima     = isset($_POST['nama_penerima'])     ? trim($_POST['nama_penerima'])        : '';
$no_hp             = isset($_POST['no_hp'])             ? trim($_POST['no_hp'])                : '';
$alamat_pengiriman = isset($_POST['alamat'])            ? trim($_POST['alamat'])               : '';
$id_lokasi = isset($_POST['id_lokasi']) ? (int) $_POST['id_lokasi'] : 0;

// ─────────────────────────────────────────
// 4. VALIDASI INPUT
// ─────────────────────────────────────────
if ($id_produk <= 0) {
  redirect_error('../user/user_produk.php', 'Produk tidak valid.');
}

if ($nama_penerima === '' || $no_hp === '') {
  redirect_error('checkout.php?id_produk=' . $id_produk, 'Nama penerima dan nomor HP wajib diisi.');
}

if ($id_lokasi <= 0) {
  redirect_error('checkout.php?id_produk=' . $id_produk, 'Lokasi kampus wajib dipilih.');
}

// ─────────────────────────────────────────
// 5. VALIDASI PRODUK
// ─────────────────────────────────────────
$stmt = $koneksi->prepare("
    SELECT
        p.id_produk,
        p.id_user    AS id_penjual,
        p.nama_produk,
        p.harga,
        p.status_produk
    FROM produk p
    WHERE p.id_produk  = ?
      AND p.status_aktif = 1
    LIMIT 1
");
$stmt->bind_param('i', $id_produk);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$produk) {
  redirect_error('../user/user_produk.php', 'Produk tidak ditemukan.');
}

if ($produk['status_produk'] !== 'tersedia') {
  redirect_error('../user/user_produk.php', 'Produk sudah tidak tersedia.');
}

if ((int) $produk['id_penjual'] === $id_pembeli) {
  redirect_error('checkout.php?id_produk=' . $id_produk, 'Anda tidak dapat membeli produk sendiri.');
}

$id_penjual   = (int)   $produk['id_penjual'];
$harga_produk = (float) $produk['harga'];

// ─────────────────────────────────────────
// 6. TOTAL BAYAR — TANPA ONGKIR (COD)
// ─────────────────────────────────────────
$ongkir      = 0.0;
$total_bayar = round($harga_produk, 2);

// ─────────────────────────────────────────
// 8. GENERATE KODE INVOICE UNIK
// ─────────────────────────────────────────
// Format: CT-YYYYMMDD-XXXXXX
function generate_invoice(mysqli $koneksi): string
{
  do {
    $kode = 'CT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    $cek  = $koneksi->prepare("SELECT id_transaksi FROM transaksi WHERE kode_invoice = ? LIMIT 1");
    $cek->bind_param('s', $kode);
    $cek->execute();
    $cek->store_result();
    $ada = $cek->num_rows > 0;
    $cek->close();
  } while ($ada);

  return $kode;
}

$kode_invoice = generate_invoice($koneksi);

// ─────────────────────────────────────────
// 9. SIMPAN KE DATABASE (TRANSACTION)
// ─────────────────────────────────────────
$koneksi->begin_transaction();

try {
  // 9a. INSERT transaksi
  // metode_pembayaran = NULL, akan diisi di tagihan.php
  $stmt = $koneksi->prepare("
    INSERT INTO transaksi
        (id_produk, id_penjual, id_pembeli,
         harga_produk, ongkir, total_bayar,
         kode_invoice,
         status_transaksi, status_pembayaran)
    VALUES
        (?, ?, ?,
         ?, ?, ?,
         ?,
         'menunggu_pembayaran', 'belum_bayar')
");
  $stmt->bind_param(
    'iiiddds',
    $id_produk,
    $id_penjual,
    $id_pembeli,
    $harga_produk,
    $ongkir,
    $total_bayar,
    $kode_invoice
  );
  $stmt->execute();
  $id_transaksi = (int) $koneksi->insert_id;
  $stmt->close();

  if ($id_transaksi <= 0) {
    throw new RuntimeException('Gagal menyimpan transaksi.');
  }

  // 9b. INSERT pengiriman
  // Jika Ambil di Tempat: alamat, lat, lng, ongkir, jarak = NULL
  $stmt = $koneksi->prepare("SELECT nama_lokasi FROM lokasi WHERE id_lokasi = ? LIMIT 1");
  $stmt->bind_param('i', $id_lokasi);
  $stmt->execute();
  $lokasi_row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $nama_lokasi_kampus = $lokasi_row ? $lokasi_row['nama_lokasi'] : '-';

  $stmt = $koneksi->prepare("
    INSERT INTO pengiriman
        (id_transaksi, nama_penerima, no_hp, alamat, status_pengiriman)
    VALUES
        (?, ?, ?, ?, 'Belum Dikirim')
");
  $stmt->bind_param(
    'isss',
    $id_transaksi,
    $nama_penerima,
    $no_hp,
    $nama_lokasi_kampus
  );
  $stmt->execute();
  $stmt->close();

  // 9c. UPDATE status produk → 'dipesan'
  // Gunakan kondisi status_produk = 'tersedia' sebagai race condition guard
  $stmt = $koneksi->prepare("
        UPDATE produk
        SET status_produk = 'dipesan'
        WHERE id_produk    = ?
          AND status_produk = 'tersedia'
    ");
  $stmt->bind_param('i', $id_produk);
  $stmt->execute();

  if ($stmt->affected_rows === 0) {
    throw new RuntimeException('Produk sudah tidak tersedia (baru saja dibeli orang lain).');
  }
  $stmt->close();

  $koneksi->commit();
} catch (RuntimeException $e) {
  $koneksi->rollback();
  redirect_error('checkout.php?id_produk=' . $id_produk, $e->getMessage());
} catch (Exception $e) {
  $koneksi->rollback();
  error_log('[proses_checkout] ' . $e->getMessage());
  redirect_error('checkout.php?id_produk=' . $id_produk, 'Terjadi kesalahan sistem. Silakan coba lagi.');
}

// ─────────────────────────────────────────
// 10. REDIRECT KE TAGIHAN
// ─────────────────────────────────────────
$_SESSION['sukses_checkout'] = 'Pesanan berhasil dibuat! Silakan selesaikan pembayaran.';
header('Location: tagihan.php?id_transaksi=' . $id_transaksi);
exit;
