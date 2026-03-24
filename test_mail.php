<?php
require_once 'config.php';

$to      = 'habib@omit.avi.id'; // Ganti dengan email tes kamu
$subject = '📧 Test Notifikasi TaskFlow Pro';
$message = "Halo! Ini adalah pesan tes dari sistem TaskFlow Pro untuk memverifikasi pengaturan email Anda.\n\n"
         . "Jika Anda menerima email ini, berarti konfigurasi pengiriman email sudah berjalan!";

echo "<h2>📧 Mengetes Pengiriman Email...</h2>";
echo "<p>Mengirim ke: <b>$to</b></p>";

if (sendEmailNotif($to, $subject, $message)) {
    echo "<p style='color:green;font-weight:bold;'>✅ BERHASIL! Silakan cek inbox/spam email Anda.</p>";
} else {
    echo "<p style='color:red;font-weight:bold;'>❌ GAGAL! Kemungkinan mail() di XAMPP belum dikonfigurasi.</p>";
    echo "<p><i>Saran: Gunakan layanan email di hosting online untuk hasil yang lebih stabil.</i></p>";
}

echo "<hr><a href='settings'>Kembali ke Pengaturan</a>";
?>
