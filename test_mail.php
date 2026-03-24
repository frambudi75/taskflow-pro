<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: ' . route('login'));
    exit;
}

$to      = $_SESSION['email'] ?? 'admin@taskflow.local';
$subject = '📧 Test Notifikasi TaskFlow Pro';
$message = "Halo! Ini adalah pesan tes dari sistem TaskFlow Pro untuk memverifikasi pengaturan email Anda.\n\n"
         . "Jika Anda menerima email ini, berarti konfigurasi pengiriman email sudah berjalan!";

// To capture PHPMailer output if it fails
$errorDetail = '';
$success = false;

try {
    $success = sendEmailNotif($to, $subject, $message);
} catch (Exception $e) {
    $errorDetail = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Email Test | TaskFlow Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-5">
    <div class="max-w-md w-full bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-xl text-center">
        <div class="mb-6">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto <?php echo $success ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'; ?>">
                <?php if ($success): ?>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <?php else: ?>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                <?php endif; ?>
            </div>
        </div>

        <h2 class="text-2xl font-black text-slate-900 mb-2">
            <?php echo $success ? 'Email Terkirim!' : 'Gagal Terkirim'; ?>
        </h2>
        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-6">Status Pengujian</p>
        
        <div class="text-left bg-slate-50 rounded-2xl p-4 mb-8">
            <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
                <span class="text-[10px] font-black text-indigo-500 uppercase">Penerima</span>
                <span class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($to); ?></span>
            </div>
            
            <?php if ($success): ?>
                <p class="text-sm text-slate-500 font-medium leading-relaxed">
                   Selamat! Konfigurasi SMTP Anda sepertinya sudah benar. Silakan cek inbox atau folder **Spam** email Anda beberapa saat lagi.
                </p>
            <?php else: ?>
                <p class="text-sm text-rose-500 font-bold mb-3 italic">"Konfigurasi SMTP atau Host kemungkinan belum di-setting dengan benar."</p>
                <div class="bg-rose-50 rounded-xl p-3 border border-rose-100/50">
                    <p class="text-[9px] text-rose-700 font-mono break-all leading-tight">
                        Log Error: Periksa error_log server untuk info PHPMailer selengkapnya.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <a href="<?php echo route('settings'); ?>" class="w-full py-4 rounded-2xl font-bold bg-slate-900 text-white block transition-all hover:bg-slate-800 active:scale-95 shadow-lg shadow-slate-900/15 text-sm">
            Kembali ke Pengaturan
        </a>
    </div>
</body>
</html>
