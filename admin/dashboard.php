<?php
// admin/dashboard.php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// =========================
// VALIDASI LOGIN
// =========================
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['id_user'])) {
  header('Location: auth/login.php');
  exit;
}
if ($_SESSION['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}

require_once 'config/Koneksi.php';
/** @var mysqli $koneksi */

// =========================
// QUERY STATISTIK USER
// =========================
$stat_user = $koneksi->query("
    SELECT
        COUNT(*)                                                         AS total,
        COUNT(CASE WHEN status_verifikasi = 'menunggu'      THEN 1 END) AS menunggu,
        COUNT(CASE WHEN status_verifikasi = 'terverifikasi' THEN 1 END) AS terverifikasi,
        COUNT(CASE WHEN status_verifikasi = 'ditolak'       THEN 1 END) AS ditolak
    FROM users WHERE role = 'user'
")->fetch_assoc();

// =========================
// QUERY STATISTIK PRODUK
// =========================
$stat_produk = $koneksi->query("
    SELECT
        COUNT(*)                                                           AS total,
        COUNT(CASE WHEN status_produk = 'menunggu'  THEN 1 END)           AS menunggu,
        COUNT(CASE WHEN status_produk = 'tersedia'  THEN 1 END)           AS tersedia,
        COUNT(CASE WHEN status_produk = 'dipesan'   THEN 1 END)           AS dipesan,
        COUNT(CASE WHEN status_produk = 'terjual'   THEN 1 END)           AS terjual,
        COUNT(CASE WHEN status_produk = 'ditolak'   THEN 1 END)           AS ditolak
    FROM produk
")->fetch_assoc();

// =========================
// QUERY STATISTIK TRANSAKSI
// =========================
$stat_trx = $koneksi->query("
    SELECT
        COUNT(*)                                                                      AS total,
        COUNT(CASE WHEN status_pembayaran = 'belum_bayar'         THEN 1 END)        AS belum_bayar,
        COUNT(CASE WHEN status_pembayaran = 'menunggu_konfirmasi' THEN 1 END)        AS menunggu_konfirmasi,
        COUNT(CASE WHEN status_pembayaran = 'dibayar'             THEN 1 END)        AS dibayar,
        COALESCE(SUM(CASE WHEN status_pembayaran = 'dibayar' THEN total_bayar END),0) AS nominal_masuk
    FROM transaksi
")->fetch_assoc();

// =========================
// QUERY PRODUK PER KATEGORI (grafik)
// =========================
$res_kat = $koneksi->query("
    SELECT k.nama_kategori, COUNT(p.id_produk) AS jumlah
    FROM kategori_barang k
    LEFT JOIN produk p ON p.id_kategori = k.id_kategori
    GROUP BY k.id_kategori, k.nama_kategori
    ORDER BY jumlah DESC
");
$label_kat = $val_kat = [];
while ($r = $res_kat->fetch_assoc()) {
  $label_kat[] = $r['nama_kategori'];
  $val_kat[]   = (int) $r['jumlah'];
}

// =========================
// QUERY TREND TRANSAKSI 7 HARI
// =========================
$res_trend = $koneksi->query("
    SELECT DATE(created_at) AS tgl, COUNT(*) AS jumlah
    FROM transaksi
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY tgl ASC
");
$trend_raw = [];
while ($r = $res_trend->fetch_assoc()) {
  $trend_raw[$r['tgl']] = (int) $r['jumlah'];
}
// Isi hari tanpa transaksi dengan 0
$label_trend = $val_trend = [];
for ($i = 6; $i >= 0; $i--) {
  $tgl           = date('Y-m-d', strtotime("-$i days"));
  $label_trend[] = date('d/m', strtotime($tgl));
  $val_trend[]   = $trend_raw[$tgl] ?? 0;
}

// =========================
// HELPER
// =========================
function rupiah(float $n): string
{
  return 'Rp ' . number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | CampusTrade Admin</title>
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body>

  <?php include 'layout/topnav.php'; ?>

  <div class="admin-wrapper">
    <div class="content">

      <!-- ════════════════════════════════════════════
           HEADER HALAMAN
      ════════════════════════════════════════════ -->
      <div class="content-header">
        <h1>Dashboard</h1>
        <p class="content-subtitle">Statistik realtime CampusTrade · <?= date('d F Y') ?></p>
      </div>

      <div class="content-body">

        <!-- ════════════════════════════════════════════
             SECTION: PENGGUNA
        ════════════════════════════════════════════ -->
        <div class="sec-head">
          <h2>Pengguna</h2>
        </div>
        <div class="dash-grid">
          <div class="dash-card" onclick="location='pengguna.php'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_user['total'] ?></div>
              <div class="lbl">Total Pengguna</div>
            </div>
          </div>
          <div class="dash-card" onclick="location='pengguna.php?filter=menunggu'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_user['menunggu'] ?></div>
              <div class="lbl">Menunggu Verifikasi</div>
            </div>
          </div>
          <div class="dash-card" onclick="location='pengguna.php?filter=terverifikasi'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_user['terverifikasi'] ?></div>
              <div class="lbl">Terverifikasi</div>
            </div>
          </div>
          <div class="dash-card" onclick="location='pengguna.php?filter=ditolak'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_user['ditolak'] ?></div>
              <div class="lbl">Ditolak</div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════
             SECTION: PRODUK
        ════════════════════════════════════════════ -->
        <div class="sec-head">
          <h2>Produk</h2>
        </div>
        <div class="dash-grid">
          <div class="dash-card" onclick="location='produk.php'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_produk['total'] ?></div>
              <div class="lbl">Total Produk</div>
            </div>
          </div>
          <div class="dash-card" onclick="location='produk.php?status=menunggu'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_produk['menunggu'] ?></div>
              <div class="lbl">Menunggu Verifikasi</div>
            </div>
          </div>
          <div class="dash-card" onclick="location='produk.php?status=tersedia'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_produk['tersedia'] ?></div>
              <div class="lbl">Tersedia</div>
            </div>
          </div>
          <div class="dash-card" onclick="location='produk.php?status=dipesan'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_produk['dipesan'] ?></div>
              <div class="lbl">Sedang Dipesan</div>
            </div>
          </div>
          <div class="dash-card" onclick="location='produk.php?status=terjual'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_produk['terjual'] ?></div>
              <div class="lbl">Terjual</div>
            </div>
          </div>
          <div class="dash-card" onclick="location='produk.php?status=ditolak'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_produk['ditolak'] ?></div>
              <div class="lbl">Ditolak</div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════
             SECTION: TRANSAKSI & PEMBAYARAN
        ════════════════════════════════════════════ -->
        <div class="sec-head">
          <h2>Transaksi & Pembayaran</h2>
        </div>
        <div class="dash-grid">
          <div class="dash-card" onclick="location='pembayaran.php'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_trx['total'] ?></div>
              <div class="lbl">Total Transaksi</div>
            </div>
          </div>
          <div class="dash-card" onclick="location='pembayaran.php?status=belum_bayar'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_trx['belum_bayar'] ?></div>
              <div class="lbl">Belum Bayar</div>
            </div>
          </div>
          <div class="dash-card" onclick="location='pembayaran.php?status=menunggu_konfirmasi'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_trx['menunggu_konfirmasi'] ?></div>
              <div class="lbl">Menunggu Konfirmasi</div>
            </div>
          </div>
          <div class="dash-card" onclick="location='pembayaran.php?status=dibayar'">
            <div class="dash-info">
              <div class="val"><?= (int)$stat_trx['dibayar'] ?></div>
              <div class="lbl">Pembayaran Berhasil</div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════
             GRAFIK STATISTIK
        ════════════════════════════════════════════ -->
        <div class="sec-head">
          <h2>Grafik Statistik</h2>
        </div>
        <div class="chart-grid">

          <!-- Grafik 1: Status Pengguna (Doughnut) -->
          <div class="chart-card">
            <h3>👥 Status Verifikasi Pengguna</h3>
            <div class="chart-wrap">
              <canvas id="chartUser"></canvas>
            </div>
          </div>

          <!-- Grafik 2: Status Produk (Bar horizontal) -->
          <div class="chart-card">
            <h3>📦 Status Produk</h3>
            <div class="chart-wrap">
              <canvas id="chartProduk"></canvas>
            </div>
          </div>

          <!-- Grafik 3: Status Transaksi (Doughnut) -->
          <div class="chart-card">
            <h3>🧾 Status Pembayaran Transaksi</h3>
            <div class="chart-wrap">
              <canvas id="chartTrx"></canvas>
            </div>
          </div>

          <!-- Grafik 4: Produk per Kategori (Bar) -->
          <div class="chart-card">
            <h3>🗂️ Produk per Kategori</h3>
            <div class="chart-wrap">
              <canvas id="chartKategori"></canvas>
            </div>
          </div>

          <!-- Grafik 5: Trend Transaksi 7 Hari (Line) — full width -->
          <div class="chart-card chart-card--full">
            <h3>📈 Trend Transaksi 7 Hari Terakhir</h3>
            <div class="chart-wrap chart-wrap--short">
              <canvas id="chartTrend"></canvas>
            </div>
          </div>

        </div>

      </div>
    </div>
  </div>

  <?php include 'layout/footer.php'; ?>

  <script>
    //  Data dari PHP 
    const dataUser = {
      labels: ['Terverifikasi', 'Menunggu', 'Ditolak'],
      values: [
        <?= (int)$stat_user['terverifikasi'] ?>,
        <?= (int)$stat_user['menunggu'] ?>,
        <?= (int)$stat_user['ditolak'] ?>
      ]
    };
    const dataProduk = {
      labels: ['Menunggu', 'Tersedia', 'Dipesan', 'Terjual', 'Ditolak'],
      values: [
        <?= (int)$stat_produk['menunggu'] ?>,
        <?= (int)$stat_produk['tersedia'] ?>,
        <?= (int)$stat_produk['dipesan'] ?>,
        <?= (int)$stat_produk['terjual'] ?>,
        <?= (int)$stat_produk['ditolak'] ?>
      ]
    };
    const dataTrx = {
      labels: ['Belum Bayar', 'Menunggu Konfirmasi', 'Dibayar'],
      values: [
        <?= (int)$stat_trx['belum_bayar'] ?>,
        <?= (int)$stat_trx['menunggu_konfirmasi'] ?>,
        <?= (int)$stat_trx['dibayar'] ?>
      ]
    };
    const dataKategori = {
      labels: <?= json_encode($label_kat, JSON_UNESCAPED_UNICODE) ?>,
      values: <?= json_encode($val_kat) ?>
    };
    const dataTrend = {
      labels: <?= json_encode($label_trend) ?>,
      values: <?= json_encode($val_trend) ?>
    };

    // ── Shared chart defaults
    Chart.defaults.font.family = 'inherit';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6b7280';

    const COLORS = {
      blue: {
        bg: 'rgba(28,99,143,.15)',
        border: '#1c638f'
      },
      green: {
        bg: 'rgba(16,185,129,.15)',
        border: '#10b981'
      },
      yellow: {
        bg: 'rgba(245,158,11,.15)',
        border: '#f59e0b'
      },
      red: {
        bg: 'rgba(239,68,68,.15)',
        border: '#ef4444'
      },
      sky: {
        bg: 'rgba(14,165,233,.15)',
        border: '#0ea5e9'
      },
      teal: {
        bg: 'rgba(20,184,166,.15)',
        border: '#14b8a6'
      },
      purple: {
        bg: 'rgba(139,92,246,.15)',
        border: '#8b5cf6'
      },
    };

    function pluginNoData(label) {
      return {
        id: 'noData',
        afterDraw(chart) {
          const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
          if (total > 0) return;
          const {
            ctx,
            chartArea: {
              left,
              top,
              width,
              height
            }
          } = chart;
          ctx.save();
          ctx.fillStyle = '#9ca3af';
          ctx.font = '13px inherit';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillText('Belum ada data', left + width / 2, top + height / 2);
          ctx.restore();
        }
      };
    }

    // GRAFIK 1: Status Pengguna (Doughnut)
    new Chart(document.getElementById('chartUser'), {
      type: 'doughnut',
      data: {
        labels: dataUser.labels,
        datasets: [{
          data: dataUser.values,
          backgroundColor: [COLORS.green.bg, COLORS.yellow.bg, COLORS.red.bg],
          borderColor: [COLORS.green.border, COLORS.yellow.border, COLORS.red.border],
          borderWidth: 2,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 16,
              boxWidth: 12
            }
          },
          tooltip: {
            callbacks: {
              label: ctx => ` ${ctx.label}: ${ctx.parsed} pengguna`
            }
          }
        }
      },
      plugins: [pluginNoData('Belum ada data')]
    });

    // GRAFIK 2: Status Produk (Bar horizontal)
    new Chart(document.getElementById('chartProduk'), {
      type: 'bar',
      data: {
        labels: dataProduk.labels,
        datasets: [{
          label: 'Jumlah Produk',
          data: dataProduk.values,
          backgroundColor: [COLORS.yellow.bg, COLORS.green.bg, COLORS.sky.bg, COLORS.teal.bg, COLORS.red.bg],
          borderColor: [COLORS.yellow.border, COLORS.green.border, COLORS.sky.border, COLORS.teal.border, COLORS.red.border],
          borderWidth: 2,
          borderRadius: 6,
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: ctx => ` ${ctx.parsed.x} produk`
            }
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            },
            grid: {
              color: '#f0f2f5'
            }
          },
          y: {
            grid: {
              display: false
            }
          }
        }
      },
      plugins: [pluginNoData()]
    });

    // GRAFIK 3: Status Pembayaran (Doughnut)
    new Chart(document.getElementById('chartTrx'), {
      type: 'doughnut',
      data: {
        labels: dataTrx.labels,
        datasets: [{
          data: dataTrx.values,
          backgroundColor: [COLORS.yellow.bg, COLORS.sky.bg, COLORS.green.bg],
          borderColor: [COLORS.yellow.border, COLORS.sky.border, COLORS.green.border],
          borderWidth: 2,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 16,
              boxWidth: 12
            }
          },
          tooltip: {
            callbacks: {
              label: ctx => ` ${ctx.label}: ${ctx.parsed} transaksi`
            }
          }
        }
      },
      plugins: [pluginNoData()]
    });

    // GRAFIK 4: Produk per Kategori (Bar)  
    const paletBorder = [
      '#1c638f', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
      '#0ea5e9', '#14b8a6', '#f97316', '#ec4899', '#6366f1'
    ];
    const paletBg = paletBorder.map(c => c + '26');

    new Chart(document.getElementById('chartKategori'), {
      type: 'bar',
      data: {
        labels: dataKategori.labels,
        datasets: [{
          label: 'Jumlah Produk',
          data: dataKategori.values,
          backgroundColor: paletBg.slice(0, dataKategori.labels.length),
          borderColor: paletBorder.slice(0, dataKategori.labels.length),
          borderWidth: 2,
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: ctx => ` ${ctx.parsed.y} produk`
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            },
            grid: {
              color: '#f0f2f5'
            }
          },
          x: {
            grid: {
              display: false
            }
          }
        }
      },
      plugins: [pluginNoData()]
    });

    // GRAFIK 5: Trend Transaksi 7 Hari (Line)
    new Chart(document.getElementById('chartTrend'), {
      type: 'line',
      data: {
        labels: dataTrend.labels,
        datasets: [{
          label: 'Transaksi',
          data: dataTrend.values,
          borderColor: '#1c638f',
          backgroundColor: 'rgba(28,99,143,.08)',
          borderWidth: 2.5,
          pointBackgroundColor: '#1c638f',
          pointRadius: 5,
          pointHoverRadius: 7,
          fill: true,
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: ctx => ` ${ctx.parsed.y} transaksi`
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            },
            grid: {
              color: '#f0f2f5'
            }
          },
          x: {
            grid: {
              display: false
            }
          }
        }
      },
      plugins: [pluginNoData()]
    });
  </script>

</body>

</html>