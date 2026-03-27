<?php
$ftp_server = "192.168.1.182";
$ftp_user_name = "taskflow";
$ftp_user_pass = "sup3rmaN";

echo "Mencoba koneksi ke $ftp_server...\n";
$conn_id = @ftp_connect($ftp_server);

if (!$conn_id) {
    die("Gagal: Tidak bisa terhubung ke server FTP.\n");
}

if (@ftp_login($conn_id, $ftp_user_name, $ftp_user_pass)) {
    echo "Sukses login sebagai $ftp_user_name.\n";
    ftp_pasv($conn_id, true);
    
    $current_dir = ftp_pwd($conn_id);
    echo "Direktori saat ini di server: $current_dir\n";
    
    // Check root files
    $contents = ftp_nlist($conn_id, ".");
    echo "Isi folder root:\n";
    print_r($contents);
} else {
    echo "Gagal: Kredensial FTP salah.\n";
}

ftp_close($conn_id);
?>
