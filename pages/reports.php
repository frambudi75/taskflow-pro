<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/reports.php - Laporan Harian Kerja Lengkap dengan Kop Surat Perusahaan, Logo, Project Linkage, Multi-Foto Utuh, Filter Periode, Kategori, Jam Kerja, Ekspor Excel/CSV & Cetak Resmi A4

$user_id = (int)$_SESSION['user_id'];
$display_name = $_SESSION['display_name'] ?? 'User';
$email = $_SESSION['email'] ?? '';
$app_name = getSetting('app_name', 'TaskFlow Pro');

// Load Company & Letterhead Settings (Feature #1)
$company_name = getSetting('company_name', $app_name);
$company_tagline = getSetting('company_tagline', 'General Contractor & Information Technology Services');
$company_address = getSetting('company_address', 'Jl. Jenderal Sudirman No. 45, Gedung Menara Sentral Lt. 8, Jakarta Selatan 12190');
$company_phone = getSetting('company_phone', '(021) 555-0199 / 0812-3456-7890');
$company_email = getSetting('company_email', 'info@perusahaan.co.id');
$company_website = getSetting('company_website', 'www.perusahaan.co.id');
$company_logo = getSetting('company_logo', '');
if (empty($company_logo)) {
    $company_logo = getSetting('app_logo', '');
}
$logo_valid_path = null;
if (!empty($company_logo)) {
    if (file_exists(__DIR__ . '/../' . $company_logo)) {
        $logo_valid_path = $company_logo;
    } elseif (file_exists($company_logo)) {
        $logo_valid_path = $company_logo;
    }
}

// Fetch user's active projects (Feature #2 - Project Linkage)
$projects_list = [];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        status VARCHAR(50) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_projects (user_id)
    )");
    $stmtProj = $pdo->prepare("SELECT id, name FROM projects WHERE user_id = ? ORDER BY name ASC");
    $stmtProj->execute([$user_id]);
    $projects_list = $stmtProj->fetchAll() ?: [];
} catch (\Throwable $e) {
    $projects_list = [];
}

// Categories definition
$categories_meta = [
    'Teknis & Maintenance'   => ['icon' => '🛠️', 'color' => 'bg-blue-50 text-blue-700 border-blue-200', 'badge' => 'border-blue-300 bg-blue-100 text-blue-800'],
    'Kunjungan Lapangan'     => ['icon' => '🚗', 'color' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'badge' => 'border-emerald-300 bg-emerald-100 text-emerald-800'],
    'Administrasi & Dokumen' => ['icon' => '📋', 'color' => 'bg-amber-50 text-amber-700 border-amber-200', 'badge' => 'border-amber-300 bg-amber-100 text-amber-800'],
    'Rapat & Koordinasi'     => ['icon' => '👥', 'color' => 'bg-purple-50 text-purple-700 border-purple-200', 'badge' => 'border-purple-300 bg-purple-100 text-purple-800'],
    'Penanganan Kendala'     => ['icon' => '🚨', 'color' => 'bg-rose-50 text-rose-700 border-rose-200', 'badge' => 'border-rose-300 bg-rose-100 text-rose-800'],
    'Logistik & Operasional' => ['icon' => '📦', 'color' => 'bg-indigo-50 text-indigo-700 border-indigo-200', 'badge' => 'border-indigo-300 bg-indigo-100 text-indigo-800'],
    'Umum'                   => ['icon' => '💡', 'color' => 'bg-slate-100 text-slate-700 border-slate-200', 'badge' => 'border-slate-300 bg-slate-200 text-slate-800'],
];

// Helpers
function getReportPhotos($raw) {
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return array_values(array_filter($decoded));
    return [$raw];
}

function calculateDurationText($start, $end) {
    if (empty($start) || empty($end)) return null;
    $s = strtotime("1970-01-01 $start");
    $e = strtotime("1970-01-01 $end");
    if ($e <= $s) return null;
    $diffMins = round(($e - $s) / 60);
    $hours = floor($diffMins / 60);
    $mins = $diffMins % 60;
    if ($hours > 0 && $mins > 0) return "{$hours}j {$mins}m";
    if ($hours > 0) return "{$hours} Jam";
    return "{$mins} Menit";
}

function calculateDurationHoursNum($start, $end) {
    if (empty($start) || empty($end)) return 0;
    $s = strtotime("1970-01-01 $start");
    $e = strtotime("1970-01-01 $end");
    if ($e <= $s) return 0;
    return ($e - $s) / 3600;
}

// Request parameters
$period = $_GET['period'] ?? 'all';
$start_date = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : null;
$end_date   = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : null;
$filter_date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : null;
$filter_category = isset($_GET['category']) && $_GET['category'] !== '' ? trim($_GET['category']) : null;
$filter_project  = isset($_GET['project_id']) && (int)$_GET['project_id'] > 0 ? (int)$_GET['project_id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$print_view = isset($_GET['print_view']) && $_GET['print_view'] === '1';
$print_layout = $_GET['layout'] ?? 'card'; // 'card' or 'table'
$print_orient = $_GET['orient'] ?? 'landscape'; // default landscape

// Handle quick preset logic
if ($filter_date && !$start_date && !$end_date) {
    $start_date = $filter_date;
    $end_date   = $filter_date;
    $period     = 'custom';
} elseif ($period === 'today') {
    $start_date = date('Y-m-d');
    $end_date   = date('Y-m-d');
} elseif ($period === 'this_week') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date   = date('Y-m-d', strtotime('sunday this week'));
} elseif ($period === 'this_month') {
    $start_date = date('Y-m-01');
    $end_date   = date('Y-m-t');
}

// Handle Add Report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_report'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $report_date     = $_POST['report_date'] ?? date('Y-m-d');
        $category        = trim($_POST['category'] ?? 'Umum');
        $project_id      = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $start_time      = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
        $end_time        = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
        $supervisor_name = !empty($_POST['supervisor_name']) ? trim($_POST['supervisor_name']) : null;
        $title           = trim($_POST['title']);
        $description     = trim($_POST['description']);
        $photo_paths     = [];

        if (!empty($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {
            $count = count($_FILES['photos']['name']);
            $upload_dir = 'uploads/' . $user_id . '/reports/';
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['photos']['tmp_name'][$i];
                    $orig_name = $_FILES['photos']['name'][$i];
                    $mime_type = $_FILES['photos']['type'][$i];
                    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($orig_name));
                    $filename = time() . '_' . $i . '_' . $safe_name;
                    $target_path = $upload_dir . $filename;

                    $opt = optimizeAndSaveFile($tmp_name, $target_path, $mime_type);
                    if ($opt['success']) {
                        $photo_paths[] = $opt['path'];
                    }
                }
            }
        }

        $photo_json = !empty($photo_paths) ? json_encode($photo_paths) : null;

        if ($title && $description) {
            $stmt = $pdo->prepare("INSERT INTO daily_reports (user_id, report_date, category, project_id, start_time, end_time, supervisor_name, title, description, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $report_date, $category, $project_id, $start_time, $end_time, $supervisor_name, $title, $description, $photo_json]);
            $report_id = $pdo->lastInsertId();
            logActivity("Menambahkan laporan harian: " . $title, "report", $report_id, ['date' => $report_date, 'category' => $category, 'project_id' => $project_id, 'photos_count' => count($photo_paths)]);
            
            $redirect_query = http_build_query(array_filter([
                'page'       => 'reports',
                'success'    => 'added',
                'period'     => $period !== 'all' ? $period : null,
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'category'   => $filter_category,
                'project_id' => $filter_project
            ]));
            header("Location: index.php?" . $redirect_query);
            exit;
        }
    }
}

// Handle Edit Report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_report'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $report_id       = (int)$_POST['report_id'];
        $report_date     = $_POST['report_date'] ?? date('Y-m-d');
        $category        = trim($_POST['category'] ?? 'Umum');
        $project_id      = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $start_time      = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
        $end_time        = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
        $supervisor_name = !empty($_POST['supervisor_name']) ? trim($_POST['supervisor_name']) : null;
        $title           = trim($_POST['title']);
        $description     = trim($_POST['description']);

        $chk = $pdo->prepare("SELECT photo_path FROM daily_reports WHERE id = ? AND user_id = ?");
        $chk->execute([$report_id, $user_id]);
        $existing = $chk->fetch();

        if ($existing && $title && $description) {
            $existing_photos = getReportPhotos($existing['photo_path']);
            $keep_photos = isset($_POST['keep_photos']) && is_array($_POST['keep_photos']) ? $_POST['keep_photos'] : [];

            // Remove deleted photos from disk
            foreach ($existing_photos as $ep) {
                if (!in_array($ep, $keep_photos, true)) {
                    if (file_exists($ep)) {
                        @unlink($ep);
                    }
                }
            }

            $final_photos = array_values(array_intersect($existing_photos, $keep_photos));

            // Upload newly selected photos
            if (!empty($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {
                $count = count($_FILES['photos']['name']);
                $upload_dir = 'uploads/' . $user_id . '/reports/';
                for ($i = 0; $i < $count; $i++) {
                    if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['photos']['tmp_name'][$i];
                        $orig_name = $_FILES['photos']['name'][$i];
                        $mime_type = $_FILES['photos']['type'][$i];
                        $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($orig_name));
                        $filename = time() . '_' . $i . '_' . $safe_name;
                        $target_path = $upload_dir . $filename;

                        $opt = optimizeAndSaveFile($tmp_name, $target_path, $mime_type);
                        if ($opt['success']) {
                            $final_photos[] = $opt['path'];
                        }
                    }
                }
            }

            $photo_json = !empty($final_photos) ? json_encode($final_photos) : null;

            $stmt = $pdo->prepare("UPDATE daily_reports SET report_date = ?, category = ?, project_id = ?, start_time = ?, end_time = ?, supervisor_name = ?, title = ?, description = ?, photo_path = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$report_date, $category, $project_id, $start_time, $end_time, $supervisor_name, $title, $description, $photo_json, $report_id, $user_id]);
            logActivity("Memperbarui laporan harian: " . $title, "report", $report_id);

            $redirect_query = http_build_query(array_filter([
                'page'       => 'reports',
                'success'    => 'updated',
                'period'     => $period !== 'all' ? $period : null,
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'category'   => $filter_category,
                'project_id' => $filter_project
            ]));
            header("Location: index.php?" . $redirect_query);
            exit;
        }
    }
}

// Handle Delete Report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_report'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $report_id = (int)$_POST['report_id'];

        $chk = $pdo->prepare("SELECT title, photo_path FROM daily_reports WHERE id = ? AND (user_id = ?" . (isAdmin() ? " OR 1=1" : "") . ")");
        $chk->execute([$report_id, $user_id]);
        $report = $chk->fetch();

        if ($report) {
            $photos = getReportPhotos($report['photo_path']);
            foreach ($photos as $p) {
                if ($p && file_exists($p)) {
                    @unlink($p);
                }
            }
            $del = $pdo->prepare("DELETE FROM daily_reports WHERE id = ?");
            $del->execute([$report_id]);
            logActivity("Menghapus laporan harian: " . $report['title'], "report_delete");

            $redirect_query = http_build_query(array_filter([
                'page'       => 'reports',
                'success'    => 'deleted',
                'period'     => $period !== 'all' ? $period : null,
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'category'   => $filter_category,
                'project_id' => $filter_project
            ]));
            header("Location: index.php?" . $redirect_query);
            exit;
        }
    }
}

// Build SQL Query with Project Join
$sql = "SELECT r.*, p.name AS project_name 
        FROM daily_reports r 
        LEFT JOIN projects p ON p.id = r.project_id 
        WHERE r.user_id = ?";
$params = [$user_id];

if ($start_date && $end_date) {
    $sql .= " AND r.report_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
} elseif ($start_date) {
    $sql .= " AND r.report_date >= ?";
    $params[] = $start_date;
} elseif ($end_date) {
    $sql .= " AND r.report_date <= ?";
    $params[] = $end_date;
}

if ($filter_category) {
    $sql .= " AND r.category = ?";
    $params[] = $filter_category;
}

if ($filter_project) {
    $sql .= " AND r.project_id = ?";
    $params[] = $filter_project;
}

if ($search) {
    $sql .= " AND (r.title LIKE ? OR r.description LIKE ? OR r.supervisor_name LIKE ? OR p.name LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql .= " ORDER BY r.report_date DESC, r.start_time ASC, r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Handle CSV / Excel Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "Laporan_Kerja_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $display_name) . "_" . ($start_date ? $start_date : 'All') . ($end_date && $end_date !== $start_date ? "_sd_" . $end_date : "") . ".csv";
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    fputcsv($output, [
        'No',
        'Tanggal',
        'Kategori',
        'Proyek Terkait',
        'Jam Mulai',
        'Jam Selesai',
        'Durasi',
        'Judul Kegiatan',
        'Rincian Kegiatan',
        'Jumlah Foto Dokumentasi',
        'Atasan / Supervisor',
        'Waktu Pencatatan'
    ]);

    $no = 1;
    foreach ($reports as $r) {
        $photos = getReportPhotos($r['photo_path']);
        $durText = calculateDurationText($r['start_time'], $r['end_time']) ?: '-';
        $startTime = !empty($r['start_time']) ? date('H:i', strtotime($r['start_time'])) : '-';
        $endTime = !empty($r['end_time']) ? date('H:i', strtotime($r['end_time'])) : '-';

        fputcsv($output, [
            $no++,
            $r['report_date'],
            $r['category'] ?: 'Umum',
            $r['project_name'] ?: '-',
            $startTime,
            $endTime,
            $durText,
            $r['title'],
            $r['description'],
            count($photos),
            $r['supervisor_name'] ?: '-',
            date('d/m/Y H:i', strtotime($r['created_at']))
        ]);
    }
    fclose($output);
    exit;
}

// Compute KPIs for filtered dataset
$total_reports = count($reports);
$total_photos = 0;
$total_hours = 0;
$category_counts = [];

foreach ($reports as $r) {
    $photos = getReportPhotos($r['photo_path']);
    $total_photos += count($photos);
    $total_hours += calculateDurationHoursNum($r['start_time'], $r['end_time']);
    $cat = $r['category'] ?: 'Umum';
    $category_counts[$cat] = ($category_counts[$cat] ?? 0) + 1;
}

arsort($category_counts);
$top_category = !empty($category_counts) ? array_key_first($category_counts) : '-';

// Calculate Period Label for Header & Print
$period_label = 'Semua Periode';
if ($start_date && $end_date && $start_date === $end_date) {
    $period_label = date('l, d F Y', strtotime($start_date));
} elseif ($start_date && $end_date) {
    $period_label = date('d M Y', strtotime($start_date)) . ' s/d ' . date('d M Y', strtotime($end_date));
} elseif ($start_date) {
    $period_label = 'Mulai ' . date('d M Y', strtotime($start_date));
}

// Group reports by date for feed display
$grouped_reports = [];
foreach ($reports as $r) {
    $grouped_reports[$r['report_date']][] = $r;
}

// =========================================================================
// STANDALONE DEDICATED PRINT VIEW (A4 PDF FORMAL KOP SURAT PERUSAHAAN)
// =========================================================================
if ($print_view) {
    $doc_number = "DOC-LHK/" . date('Y/m') . "/" . str_pad((string)$user_id, 4, '0', STR_PAD_LEFT);
    $is_landscape = $print_orient === 'landscape';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Laporan Kegiatan - <?php echo htmlspecialchars($display_name); ?> (<?php echo htmlspecialchars($period_label); ?>)</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        <style id="pageStyle">
            @page {
                size: A4 <?php echo $is_landscape ? 'landscape' : 'portrait'; ?>;
                margin: <?php echo $is_landscape ? '10mm 12mm 12mm 12mm' : '12mm 12mm 15mm 12mm'; ?>;
            }
        </style>
        <style>
            * {
                box-sizing: border-box;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
                background: #f1f5f9;
                color: #0f172a;
                margin: 0;
                padding: 16px;
                font-size: 11px;
                line-height: 1.5;
            }
            .paper {
                max-width: <?php echo $is_landscape ? '1120px' : '820px'; ?>;
                margin: 0 auto;
                background: #ffffff;
                padding: <?php echo $is_landscape ? '26px 36px' : '30px 36px'; ?>;
                border-radius: 8px;
                box-shadow: 0 4px 24px rgba(0,0,0,0.08);
                transition: max-width 0.2s ease;
            }
            .screen-actions {
                max-width: <?php echo $is_landscape ? '1120px' : '820px'; ?>;
                margin: 0 auto 16px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                background: #ffffff;
                padding: 12px 18px;
                border-radius: 12px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 16px;
                font-size: 12px;
                font-weight: 700;
                border-radius: 8px;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.15s ease;
                border: none;
            }
            .btn-primary { background: #4f46e5; color: #fff; }
            .btn-primary:hover { background: #4338ca; }
            .btn-secondary { background: #e2e8f0; color: #334155; }
            .btn-secondary:hover { background: #cbd5e1; }
            .btn-pill {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 6px 12px;
                font-size: 11px;
                font-weight: 700;
                border-radius: 6px;
                background: #f1f5f9;
                color: #475569;
                border: 1px solid #cbd5e1;
                cursor: pointer;
                text-decoration: none;
            }
            .btn-pill.active {
                background: #4f46e5;
                color: #fff;
                border-color: #4f46e5;
            }
            
            /* Kop Surat Resmi dengan Logo & Detail Kontak */
            .header-kop {
                border-bottom: 3px double #0f172a;
                padding-bottom: 12px;
                margin-bottom: 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 20px;
            }
            .kop-brand-wrap {
                display: flex;
                align-items: center;
                gap: 16px;
            }
            .kop-logo {
                max-width: 90px;
                max-height: 70px;
                object-fit: contain;
            }
            .kop-info h1 {
                font-size: 18px;
                font-weight: 900;
                margin: 0;
                color: #0f172a;
                text-transform: uppercase;
                letter-spacing: -0.3px;
                line-height: 1.2;
            }
            .kop-info .tagline {
                font-size: 10.5px;
                font-weight: 700;
                color: #4f46e5;
                margin: 2px 0 3px;
            }
            .kop-info .address {
                font-size: 9px;
                color: #475569;
                line-height: 1.35;
            }
            .kop-doc-meta {
                text-align: right;
                font-size: 9px;
                color: #64748b;
                line-height: 1.4;
                flex-shrink: 0;
            }
            .kop-doc-meta .title {
                font-size: 11.5px;
                font-weight: 800;
                color: #0f172a;
                text-transform: uppercase;
                margin-bottom: 2px;
            }

            .meta-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 18px;
                font-size: 10.5px;
            }
            .meta-table td {
                padding: 6px 10px;
                border: 1px solid #cbd5e1;
            }
            .meta-table .label {
                background: #f8fafc;
                font-weight: 700;
                width: 20%;
                color: #334155;
            }

            /* Activity Card Container (Integrated Photo & Details) */
            .activity-card {
                border: 1.5px solid #cbd5e1;
                border-radius: 8px;
                margin-bottom: 16px;
                background: #ffffff;
                page-break-inside: avoid;
                overflow: hidden;
            }
            .activity-card-header {
                background: #f1f5f9;
                border-bottom: 1px solid #cbd5e1;
                padding: 8px 12px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .activity-card-title {
                font-size: 12px;
                font-weight: 800;
                color: #0f172a;
            }
            .activity-card-body {
                padding: 12px;
            }
            .activity-description {
                font-size: 10.5px;
                line-height: 1.6;
                color: #1e293b;
                white-space: pre-line;
                margin-bottom: 10px;
            }
            
            /* Responsive Grid inside Landscape vs Portrait */
            .card-landscape-grid {
                display: <?php echo $is_landscape ? 'grid' : 'block'; ?>;
                grid-template-columns: <?php echo $is_landscape ? '1.1fr 1fr' : '1fr'; ?>;
                gap: 16px;
            }

            /* Photo Grid Box with COMPLETE UN-CROPPED Images */
            .photo-grid-box {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 10px;
            }
            .photo-grid-title {
                font-size: 9.5px;
                font-weight: 800;
                text-transform: uppercase;
                color: #475569;
                margin-bottom: 8px;
            }
            .photo-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                align-items: start;
            }
            .photo-item {
                text-align: center;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                overflow: hidden;
                background: #ffffff;
                display: flex;
                flex-direction: column;
            }
            .photo-img-wrap {
                width: 100%;
                min-height: 140px;
                max-height: 230px;
                background: #f8fafc;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 6px;
            }
            .photo-item img {
                max-width: 100%;
                max-height: 215px;
                width: auto;
                height: auto;
                object-fit: contain !important;
                display: block;
                margin: 0 auto;
                border-radius: 3px;
            }
            .photo-caption {
                font-size: 8.5px;
                color: #475569;
                padding: 4px 6px;
                background: #f1f5f9;
                font-weight: 700;
                border-top: 1px solid #e2e8f0;
            }

            /* Integrated Table View */
            .data-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                font-size: 10px;
            }
            .data-table th {
                background: #f1f5f9;
                border: 1px solid #94a3b8;
                padding: 7px 8px;
                text-align: left;
                font-weight: 800;
                color: #0f172a;
            }
            .data-table td {
                border: 1px solid #cbd5e1;
                padding: 8px;
                vertical-align: top;
            }

            /* Signature Block */
            .signatures {
                margin-top: 32px;
                display: flex;
                justify-content: space-between;
                page-break-inside: avoid;
            }
            .sign-box {
                text-align: center;
                width: 240px;
                font-size: 11px;
            }
            .sign-space {
                margin-top: 55px;
                font-weight: 800;
                border-bottom: 1.5px solid #0f172a;
                padding-bottom: 2px;
            }

            @media print {
                body {
                    background: #ffffff;
                    padding: 0;
                }
                .paper {
                    box-shadow: none;
                    padding: 0;
                    max-width: 100%;
                }
                .screen-actions {
                    display: none !important;
                }
            }
        </style>
    </head>
    <body>
        <!-- Top Toolbar (Visible on Screen, Hidden during Print) -->
        <div class="screen-actions">
            <!-- Orientation Toggle -->
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 11px; font-weight: bold; color: #64748b;">Orientasi:</span>
                <a href="<?php echo 'index.php?' . http_build_query(array_merge($_GET, ['orient' => 'landscape'])); ?>" 
                   class="btn-pill <?php echo $is_landscape ? 'active' : ''; ?>" title="Cetak Format Melebar (Lanskap)">
                    🖼️ Lanskap (Horizontal)
                </a>
                <a href="<?php echo 'index.php?' . http_build_query(array_merge($_GET, ['orient' => 'portrait'])); ?>" 
                   class="btn-pill <?php echo !$is_landscape ? 'active' : ''; ?>" title="Cetak Format Tegak (Potret)">
                    📄 Potret (Vertikal)
                </a>
            </div>

            <!-- Layout Format Toggle -->
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 11px; font-weight: bold; color: #64748b;">Format:</span>
                <a href="<?php echo 'index.php?' . http_build_query(array_merge($_GET, ['layout' => 'card'])); ?>" 
                   class="btn-pill <?php echo $print_layout === 'card' ? 'active' : ''; ?>">
                    📑 Kartu Kegiatan
                </a>
                <a href="<?php echo 'index.php?' . http_build_query(array_merge($_GET, ['layout' => 'table'])); ?>" 
                   class="btn-pill <?php echo $print_layout === 'table' ? 'active' : ''; ?>">
                    📊 Tabel Terpadu
                </a>
            </div>

            <!-- Actions -->
            <div style="display: flex; align-items: center; gap: 8px;">
                <a href="javascript:window.close()" class="btn btn-secondary">
                    ← Tutup
                </a>
                <button onclick="window.print()" class="btn btn-primary">
                    🖨️ Simpan PDF / Cetak (<?php echo $is_landscape ? 'Lanskap' : 'Potret'; ?>)
                </button>
            </div>
        </div>

        <div class="paper">
            <!-- Kop Surat Resmi (Official Company Header) -->
            <div class="header-kop">
                <div class="kop-brand-wrap">
                    <?php if (!empty($logo_valid_path)): ?>
                        <img src="<?php echo htmlspecialchars($logo_valid_path); ?>?v=<?php echo time(); ?>" alt="Logo Perusahaan" class="kop-logo">
                    <?php endif; ?>
                    <div class="kop-info">
                        <h1><?php echo htmlspecialchars($company_name); ?></h1>
                        <?php if ($company_tagline): ?>
                            <div class="tagline"><?php echo htmlspecialchars($company_tagline); ?></div>
                        <?php endif; ?>
                        <div class="address">
                            <?php echo htmlspecialchars($company_address); ?>
                            <?php if ($company_phone || $company_email): ?>
                                <br>Telp: <?php echo htmlspecialchars($company_phone); ?> | Email: <?php echo htmlspecialchars($company_email); ?>
                            <?php endif; ?>
                            <?php if ($company_website): ?>
                                | Web: <?php echo htmlspecialchars($company_website); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="kop-doc-meta">
                    <div class="title">LAPORAN KERJA HARIAN</div>
                    <div style="font-weight: bold; color: #0f172a;">No: <?php echo $doc_number; ?></div>
                    <div>Tgl Cetak: <?php echo date('d/m/Y H:i'); ?> WIB</div>
                </div>
            </div>

            <!-- Metadata Table -->
            <table class="meta-table">
                <tr>
                    <td class="label">Nama Pelaksana</td>
                    <td style="width: 30%; font-weight: bold;"><?php echo htmlspecialchars($display_name); ?></td>
                    <td class="label">Total Kegiatan</td>
                    <td style="width: 26%; font-weight: bold;"><?php echo $total_reports; ?> Kegiatan Tercatat</td>
                </tr>
                <tr>
                    <td class="label">Periode Kegiatan</td>
                    <td style="font-weight: bold; color: #4338ca;"><?php echo htmlspecialchars($period_label); ?></td>
                    <td class="label">Akumulasi Waktu &amp; Foto</td>
                    <td><?php echo round($total_hours, 1); ?> Jam Kerja · <?php echo $total_photos; ?> Foto</td>
                </tr>
            </table>

            <?php if (empty($reports)): ?>
                <div style="text-align: center; padding: 40px; border: 1px dashed #cbd5e1; border-radius: 8px; color: #64748b;">
                    Tidak ada catatan kegiatan pada periode ini.
                </div>
            <?php else: ?>

                <!-- MODE 1: INTEGRATED ACTIVITY CARDS WITH COMPLETE UN-CROPPED PHOTOS -->
                <?php if ($print_layout === 'card'): ?>
                    <?php $itemNo = 1; foreach ($reports as $r): 
                        $photos = getReportPhotos($r['photo_path']);
                        $photos_count = count($photos);
                        $durText = calculateDurationText($r['start_time'], $r['end_time']);
                        $timeStr = !empty($r['start_time']) && !empty($r['end_time']) 
                            ? date('H:i', strtotime($r['start_time'])) . ' - ' . date('H:i', strtotime($r['end_time'])) 
                            : (!empty($r['start_time']) ? 'Mulai: ' . date('H:i', strtotime($r['start_time'])) : null);
                    ?>
                    <div class="activity-card">
                        <!-- Card Header -->
                        <div class="activity-card-header">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="background: #4f46e5; color: #fff; font-weight: 900; font-size: 10px; padding: 2px 7px; border-radius: 4px;">
                                    #<?php echo $itemNo++; ?>
                                </span>
                                <span class="activity-card-title"><?php echo htmlspecialchars($r['title']); ?></span>
                            </div>
                            <div style="font-size: 9.5px; font-weight: 700; color: #475569;">
                                📅 <?php echo date('d/m/Y', strtotime($r['report_date'])); ?>
                                <?php if ($timeStr): ?>
                                    &nbsp;·&nbsp; ⏱️ <?php echo $timeStr; ?> <?php echo $durText ? "($durText)" : ""; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="activity-card-body">
                            <div class="card-landscape-grid">
                                <!-- Left Column: Details & Description -->
                                <div>
                                    <!-- Info Badges -->
                                    <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; font-size: 9px;">
                                        <span style="padding: 2px 8px; background: #e0e7ff; color: #3730a3; font-weight: bold; border-radius: 4px; border: 1px solid #c7d2fe;">
                                            🏷️ Kategori: <?php echo htmlspecialchars($r['category'] ?: 'Umum'); ?>
                                        </span>
                                        <?php if (!empty($r['project_name'])): ?>
                                        <span style="padding: 2px 8px; background: #f0fdf4; color: #166534; font-weight: bold; border-radius: 4px; border: 1px solid #bbf7d0;">
                                            📁 Proyek: <?php echo htmlspecialchars($r['project_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['supervisor_name'])): ?>
                                        <span style="padding: 2px 8px; background: #fae8ff; color: #86198f; font-weight: bold; border-radius: 4px; border: 1px solid #f5d0fe;">
                                            👤 Pengawas: <?php echo htmlspecialchars($r['supervisor_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Description -->
                                    <div style="font-weight: 700; font-size: 9.5px; color: #475569; margin-bottom: 3px; text-transform: uppercase;">Uraian &amp; Hasil Kegiatan:</div>
                                    <div class="activity-description"><?php echo htmlspecialchars($r['description']); ?></div>
                                </div>

                                <!-- Right Column (or Bottom): Integrated Photos Gallery with 100% Intact Aspect Ratio -->
                                <?php if (!empty($photos)): ?>
                                <div class="photo-grid-box">
                                    <div class="photo-grid-title">📸 Dokumentasi Foto Kegiatan (<?php echo $photos_count; ?> Foto):</div>
                                    <div class="photo-grid" style="<?php echo $photos_count === 1 ? 'grid-template-columns: 1fr;' : ''; ?>">
                                        <?php foreach ($photos as $pIdx => $pPath): 
                                            $pUrl = 'serve_file.php?file=' . rawurlencode(str_replace('\\', '/', $pPath));
                                        ?>
                                        <div class="photo-item">
                                            <div class="photo-img-wrap">
                                                <img src="<?php echo htmlspecialchars($pUrl); ?>" alt="Foto Dokumentasi #<?php echo $pIdx + 1; ?>">
                                            </div>
                                            <div class="photo-caption">Dokumentasi #<?php echo $pIdx + 1; ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                <!-- MODE 2: INTEGRATED TABLE WITH COMPLETE UN-CROPPED PHOTO COLUMN -->
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 25px; text-align: center;">No</th>
                                <th style="width: 75px;">Tanggal &amp; Jam</th>
                                <th style="width: 95px;">Kategori &amp; Proyek</th>
                                <th>Uraian &amp; Hasil Pekerjaan</th>
                                <th style="width: <?php echo $is_landscape ? '320px' : '220px'; ?>;">Foto Dokumentasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $tIndex = 1; foreach ($reports as $tr): 
                                $tPhotos = getReportPhotos($tr['photo_path']);
                                $tTimeStr = !empty($tr['start_time']) && !empty($tr['end_time']) 
                                    ? date('H:i', strtotime($tr['start_time'])) . '-' . date('H:i', strtotime($tr['end_time'])) 
                                    : (!empty($tr['start_time']) ? date('H:i', strtotime($tr['start_time'])) : '-');
                            ?>
                            <tr>
                                <td style="text-align: center; font-weight: bold;"><?php echo $tIndex++; ?></td>
                                <td>
                                    <div style="font-weight: 700;"><?php echo date('d/m/Y', strtotime($tr['report_date'])); ?></div>
                                    <div style="font-size: 9px; color: #64748b;"><?php echo $tTimeStr; ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($tr['category'] ?: 'Umum'); ?></div>
                                    <?php if (!empty($tr['project_name'])): ?>
                                        <div style="font-size: 8.5px; color: #166534; font-weight: 600;">📁 <?php echo htmlspecialchars($tr['project_name']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($tr['supervisor_name'])): ?>
                                        <div style="font-size: 8.5px; color: #6366f1;">👤 <?php echo htmlspecialchars($tr['supervisor_name']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 800; color: #0f172a; margin-bottom: 3px; font-size: 11px;"><?php echo htmlspecialchars($tr['title']); ?></div>
                                    <div style="color: #334155; font-size: 10px; line-height: 1.45;"><?php echo nl2br(htmlspecialchars($tr['description'])); ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($tPhotos)): ?>
                                        <div style="display: grid; grid-template-columns: <?php echo count($tPhotos) > 1 ? 'repeat(2, 1fr)' : '1fr'; ?>; gap: 6px;">
                                            <?php foreach ($tPhotos as $pIdx => $pPath): 
                                                $pUrl = 'serve_file.php?file=' . rawurlencode(str_replace('\\', '/', $pPath));
                                            ?>
                                            <div style="border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; text-align: center; background: #f8fafc; padding: 4px;">
                                                <img src="<?php echo htmlspecialchars($pUrl); ?>" style="max-width: 100%; max-height: 130px; width: auto; height: auto; object-fit: contain !important; display: block; margin: 0 auto;" alt="Foto <?php echo $pIdx + 1; ?>">
                                                <div style="font-size: 8px; color: #475569; background: #f1f5f9; padding: 2px; margin-top: 3px; font-weight: 700; border-radius: 2px;">Foto #<?php echo $pIdx + 1; ?></div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="color: #94a3b8; font-size: 9px; font-style: italic; text-align: center;">(Tidak ada foto)</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <?php endif; ?>

            <!-- Formal Signature Block -->
            <div class="signatures">
                <div class="sign-box">
                    <div>Dibuat &amp; Dilaporkan Oleh,</div>
                    <div class="sign-space"><?php echo htmlspecialchars($display_name); ?></div>
                    <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">Pelaksana Pekerjaan</div>
                </div>

                <div class="sign-box">
                    <div>Diperiksa &amp; Disetujui Oleh,</div>
                    <div class="sign-space">( .................................................. )</div>
                    <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">Atasan Langsung / Pengawas</div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<div class="p-4 md:p-8 w-full max-w-7xl mx-auto space-y-6">

    <!-- Header Banner -->
    <div class="rounded-3xl p-6 md:p-9 relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-5 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white shadow-lg border border-slate-800">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-400/20 text-indigo-300 text-xs font-bold uppercase tracking-wider mb-3">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Dokumentasi, Durasi &amp; Rekap Kerja
            </div>
            <h1 class="text-2xl md:text-3xl font-extrabold text-white leading-tight mb-1">Laporan Harian Kerja</h1>
            <p class="text-slate-300 text-sm max-w-2xl leading-relaxed">Catat kegiatan harian, kategori tugas, tautan proyek, durasi pengerjaan, foto dokumentasi utuh, serta cetak lembar kerja resmi.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 flex-shrink-0">
            <?php if (!empty($reports)): ?>
            <!-- Export Excel Button -->
            <?php 
                $exportParams = $_GET;
                $exportParams['export'] = 'csv';
                $exportUrl = 'index.php?' . http_build_query($exportParams);
            ?>
            <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="px-4 py-2.5 rounded-xl bg-emerald-600/90 hover:bg-emerald-600 border border-emerald-500/30 text-white text-xs font-bold transition-all flex items-center gap-2 shadow-sm active:scale-95 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Ekspor Excel
            </a>

            <!-- Dedicated Print Button (Opens Clean Print Window in Landscape by Default) -->
            <?php 
                $printParams = $_GET;
                $printParams['print_view'] = '1';
                $printParams['orient'] = 'landscape';
                $printViewUrl = 'index.php?' . http_build_query($printParams);
            ?>
            <button type="button" onclick="openPrintWindow('<?php echo htmlspecialchars($printViewUrl); ?>')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 border border-indigo-400/30 text-white text-xs font-extrabold transition-all flex items-center gap-2 shadow-sm active:scale-95 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Cetak Lembar Resmi
            </button>
            <?php endif; ?>

            <button type="button" onclick="openAddReportModal()" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 text-white text-xs font-extrabold transition-transform active:scale-95 flex items-center gap-2 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Tulis Laporan
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['success'])): ?>
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-2xl px-5 py-4">
        <div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600 flex-shrink-0">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p class="text-emerald-900 text-sm font-bold">
            <?php 
            if($_GET['success'] === 'added') echo "Laporan harian berhasil disimpan dengan foto dokumentasi & rincian waktu."; 
            if($_GET['success'] === 'updated') echo "Laporan harian berhasil diperbarui."; 
            if($_GET['success'] === 'deleted') echo "Laporan harian telah dihapus."; 
            ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- KPI Summary Cards for Current Selection -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-4 md:p-5 border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 font-bold text-xl">
                📋
            </div>
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Kegiatan</p>
                <p class="text-xl font-black text-slate-900"><?php echo $total_reports; ?> <span class="text-xs font-semibold text-slate-400">tugas</span></p>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-4 md:p-5 border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 font-bold text-xl">
                ⏱️
            </div>
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Akumulasi Waktu</p>
                <p class="text-xl font-black text-slate-900"><?php echo round($total_hours, 1); ?> <span class="text-xs font-semibold text-slate-400">jam kerja</span></p>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-4 md:p-5 border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center flex-shrink-0 font-bold text-xl">
                📸
            </div>
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Dokumentasi</p>
                <p class="text-xl font-black text-slate-900"><?php echo $total_photos; ?> <span class="text-xs font-semibold text-slate-400">foto tersimpan</span></p>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-4 md:p-5 border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0 font-bold text-xl">
                🏆
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Fokus Utama</p>
                <p class="text-sm font-extrabold text-slate-900 truncate" title="<?php echo htmlspecialchars($top_category); ?>">
                    <?php echo htmlspecialchars($top_category); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Period Presets & Filter Controls -->
    <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm space-y-4">
        
        <!-- Period Preset Pills -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mr-1">Periode:</span>
                
                <a href="?page=reports&period=all<?php echo $filter_category ? '&category=' . urlencode($filter_category) : ''; ?><?php echo $filter_project ? '&project_id=' . $filter_project : ''; ?>"
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all <?php echo $period === 'all' && !$filter_date && !$start_date ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                    Semua
                </a>

                <a href="?page=reports&period=today<?php echo $filter_category ? '&category=' . urlencode($filter_category) : ''; ?><?php echo $filter_project ? '&project_id=' . $filter_project : ''; ?>"
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all <?php echo $period === 'today' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                    Hari Ini
                </a>

                <a href="?page=reports&period=this_week<?php echo $filter_category ? '&category=' . urlencode($filter_category) : ''; ?><?php echo $filter_project ? '&project_id=' . $filter_project : ''; ?>"
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all <?php echo $period === 'this_week' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                    Minggu Ini
                </a>

                <a href="?page=reports&period=this_month<?php echo $filter_category ? '&category=' . urlencode($filter_category) : ''; ?><?php echo $filter_project ? '&project_id=' . $filter_project : ''; ?>"
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all <?php echo $period === 'this_month' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                    Bulan Ini
                </a>
            </div>

            <div class="text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-100 px-3 py-1 rounded-xl">
                📍 Filter Aktif: <span class="font-extrabold"><?php echo htmlspecialchars($period_label); ?></span>
            </div>
        </div>

        <!-- Custom Date Range, Project & Search Form -->
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="period" value="custom">

            <!-- Start Date -->
            <div>
                <label for="startDateInput" class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Dari Tanggal</label>
                <input type="date" id="startDateInput" name="start_date" value="<?php echo htmlspecialchars($start_date ?? ''); ?>"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <!-- End Date -->
            <div>
                <label for="endDateInput" class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Sampai Tanggal</label>
                <input type="date" id="endDateInput" name="end_date" value="<?php echo htmlspecialchars($end_date ?? ''); ?>"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <!-- Category Filter -->
            <div>
                <label for="categoryFilterInput" class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Kategori</label>
                <select id="categoryFilterInput" name="category" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories_meta as $catName => $catMeta): ?>
                    <option value="<?php echo htmlspecialchars($catName); ?>" <?php echo $filter_category === $catName ? 'selected' : ''; ?>>
                        <?php echo $catMeta['icon'] . ' ' . htmlspecialchars($catName); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Project Filter -->
            <div>
                <label for="projectFilterInput" class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Proyek Terkait</label>
                <select id="projectFilterInput" name="project_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Semua Proyek</option>
                    <?php foreach ($projects_list as $prj): ?>
                    <option value="<?php echo $prj['id']; ?>" <?php echo $filter_project === (int)$prj['id'] ? 'selected' : ''; ?>>
                        📁 <?php echo htmlspecialchars($prj['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Keyword Search -->
            <div>
                <label for="searchInput" class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Cari Kata Kunci</label>
                <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Judul, uraian, proyek..."
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder:text-slate-400">
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2.5 rounded-xl text-xs font-extrabold text-white bg-slate-800 hover:bg-slate-700 transition-colors text-center shadow-sm">
                    Terapkan
                </button>
                <?php if ($start_date || $end_date || $filter_category || $filter_project || $search): ?>
                    <a href="?page=reports" class="px-3 py-2.5 rounded-xl text-xs font-bold text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 transition-colors" title="Reset Filter">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Reports Feed / List -->
    <?php if (empty($reports)): ?>
        <div class="bg-white rounded-3xl border border-slate-200 p-16 text-center shadow-sm">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 bg-indigo-50 text-indigo-600 text-3xl">
                📝
            </div>
            <h3 class="text-slate-800 font-extrabold text-base mb-1">Tidak Ada Laporan yang Ditemukan</h3>
            <p class="text-slate-400 text-xs max-w-sm mx-auto mb-6">Belum ada kegiatan pada kriteria filter ini atau Anda belum membuat laporan kerja.</p>
            <button type="button" onclick="openAddReportModal()" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-transform active:scale-95 shadow-sm inline-flex items-center gap-2">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Tulis Laporan Sekarang
            </button>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($grouped_reports as $date_group => $day_items): 
                $formatted_date_group = date('l, d F Y', strtotime($date_group));
                
                // Group Day Subtotals
                $day_hours = 0;
                $day_photos = 0;
                foreach ($day_items as $di) {
                    $day_hours += calculateDurationHoursNum($di['start_time'], $di['end_time']);
                    $day_photos += count(getReportPhotos($di['photo_path']));
                }
            ?>
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                <!-- Group Header -->
                <div class="px-6 py-4 bg-slate-50/90 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex flex-col items-center justify-center font-black leading-none shadow-sm">
                            <span class="text-xs"><?php echo date('d', strtotime($date_group)); ?></span>
                            <span class="text-[8px] uppercase"><?php echo date('M', strtotime($date_group)); ?></span>
                        </div>
                        <div>
                            <h2 class="text-sm font-extrabold text-slate-900"><?php echo $formatted_date_group; ?></h2>
                            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                                <span><?php echo count($day_items); ?> Kegiatan</span>
                                <?php if ($day_hours > 0): ?>
                                <span>·</span>
                                <span class="text-emerald-700">⏱️ <?php echo round($day_hours, 1); ?> Jam</span>
                                <?php endif; ?>
                                <?php if ($day_photos > 0): ?>
                                <span>·</span>
                                <span class="text-indigo-700">📸 <?php echo $day_photos; ?> Foto</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Print Day Button (Opens clean printable popup in Landscape) -->
                    <button type="button" onclick="printDateGroup('<?php echo $date_group; ?>')" class="px-3.5 py-1.5 rounded-xl bg-white border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 text-slate-700 text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Cetak Tanggal Ini
                    </button>
                </div>

                <!-- Items in Day -->
                <div class="divide-y divide-slate-100">
                    <?php foreach ($day_items as $item): 
                        $photos = getReportPhotos($item['photo_path']);
                        $photos_count = count($photos);
                        $item_cat = $item['category'] ?: 'Umum';
                        $cat_style = $categories_meta[$item_cat] ?? $categories_meta['Umum'];
                        $dur_text = calculateDurationText($item['start_time'], $item['end_time']);
                    ?>
                    <div class="p-6 hover:bg-slate-50/40 transition-colors flex flex-col gap-4">
                        
                        <!-- Top Row: Title, Category Badge, Time Badge & Actions -->
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <!-- Category Badge -->
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold border <?php echo $cat_style['color']; ?>">
                                        <span><?php echo $cat_style['icon']; ?></span>
                                        <span><?php echo htmlspecialchars($item_cat); ?></span>
                                    </span>

                                    <!-- Project Badge -->
                                    <?php if (!empty($item['project_name'])): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        📁 <?php echo htmlspecialchars($item['project_name']); ?>
                                    </span>
                                    <?php endif; ?>

                                    <!-- Time & Duration Badge -->
                                    <?php if (!empty($item['start_time']) && !empty($item['end_time'])): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        ⏱️ <?php echo date('H:i', strtotime($item['start_time'])); ?> - <?php echo date('H:i', strtotime($item['end_time'])); ?>
                                        <?php if ($dur_text): ?>
                                            <span class="text-indigo-600 font-extrabold">(<?php echo $dur_text; ?>)</span>
                                        <?php endif; ?>
                                    </span>
                                    <?php elseif (!empty($item['start_time'])): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        ⏱️ Mulai: <?php echo date('H:i', strtotime($item['start_time'])); ?>
                                    </span>
                                    <?php endif; ?>

                                    <!-- Supervisor Badge -->
                                    <?php if (!empty($item['supervisor_name'])): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                        👤 Pengawas: <?php echo htmlspecialchars($item['supervisor_name']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>

                                <h3 class="text-base font-extrabold text-slate-900 leading-snug">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </h3>

                                <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400">
                                    <span>Dicatat <?php echo date('H:i, d M Y', strtotime($item['created_at'])); ?></span>
                                    <?php if ($photos_count > 0): ?>
                                    <span>·</span>
                                    <span class="text-indigo-600 font-extrabold bg-indigo-50 px-1.5 py-0.5 rounded">📸 <?php echo $photos_count; ?> Foto Dokumentasi</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <button type="button" onclick='openEditReportModal(<?php echo json_encode($item); ?>)'
                                        title="Edit Laporan" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 transition-colors flex items-center justify-center">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>

                                <form method="POST" onsubmit="return confirm('Hapus laporan ini?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="delete_report" value="1">
                                    <input type="hidden" name="report_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" title="Hapus Laporan" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-rose-50 text-slate-400 hover:text-rose-600 transition-colors flex items-center justify-center">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Description text -->
                        <div class="text-xs text-slate-700 font-medium leading-relaxed whitespace-pre-line bg-slate-50/80 p-4 rounded-2xl border border-slate-100">
                            <?php echo htmlspecialchars($item['description']); ?>
                        </div>

                        <!-- Photo Gallery (Supports Multiple Photos) -->
                        <?php if ($photos_count > 0): ?>
                        <div>
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-2">Foto Dokumentasi Kegiatan (<?php echo $photos_count; ?>):</div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                <?php foreach ($photos as $pIndex => $pPath): 
                                    $pUrl = 'serve_file.php?file=' . rawurlencode(str_replace('\\', '/', $pPath));
                                ?>
                                <div class="group/img relative h-32 rounded-2xl overflow-hidden bg-slate-100 border border-slate-200 cursor-pointer shadow-sm flex items-center justify-center"
                                     onclick="window.open('<?php echo htmlspecialchars($pUrl); ?>', '_blank')">
                                    <img src="<?php echo htmlspecialchars($pUrl); ?>" alt="Dokumentasi <?php echo $pIndex + 1; ?>"
                                         class="w-full h-full object-cover group-hover/img:scale-105 transition-transform duration-300">
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover/img:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-bold">
                                        🔍 Perbesar
                                    </div>
                                    <span class="absolute bottom-1.5 left-1.5 px-1.5 py-0.5 rounded-md bg-black/60 text-white text-[9px] font-black">
                                        #<?php echo $pIndex + 1; ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- Add/Edit Report Modal (Supports Category, Project, Time, Supervisor & Multi-Photos) -->
<div id="reportModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-black/60 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
    <div class="bg-white rounded-3xl max-w-2xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900" id="reportModalTitle">Tambah Laporan Harian</h3>
                    <p class="text-xs text-slate-400 font-medium">Lengkap dengan kategori, tautan proyek &amp; multi-foto dokumentasi</p>
                </div>
            </div>
            <button type="button" onclick="closeReportModal()" aria-label="Tutup modal" class="w-8 h-8 rounded-xl hover:bg-slate-200 transition-colors flex items-center justify-center text-slate-500">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <form method="POST" enctype="multipart/form-data" id="reportForm" data-no-ajax="true" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="add_report" id="formActionInput" value="1">
                <input type="hidden" name="report_id" id="reportIdInput" value="">
                <div id="hiddenKeepPhotosContainer"></div>

                <!-- Row 1: Date & Category -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Tanggal Kegiatan *</label>
                        <input type="date" name="report_date" id="reportDateInput" required value="<?php echo date('Y-m-d'); ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Kategori Kegiatan *</label>
                        <select name="category" id="reportCategoryInput" required
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <?php foreach ($categories_meta as $catKey => $catObj): ?>
                            <option value="<?php echo htmlspecialchars($catKey); ?>">
                                <?php echo $catObj['icon'] . ' ' . htmlspecialchars($catKey); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2: Linked Project (Feature #2) -->
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Tautkan ke Proyek (Opsional)</label>
                    <select name="project_id" id="reportProjectInput"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Tanpa Proyek Terkait (Umum) --</option>
                        <?php foreach ($projects_list as $prj): ?>
                        <option value="<?php echo $prj['id']; ?>">
                            📁 <?php echo htmlspecialchars($prj['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Row 3: Time Start, End & Duration Indicator -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Jam Mulai (Opsional)</label>
                        <input type="time" name="start_time" id="reportStartTimeInput" onchange="updateLiveDuration()"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Jam Selesai (Opsional)</label>
                        <input type="time" name="end_time" id="reportEndTimeInput" onchange="updateLiveDuration()"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Live Duration Pill Indicator -->
                <div id="liveDurationBadge" class="hidden items-center gap-2 p-2.5 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-extrabold">
                    <span>⏱️ Estimasi Durasi:</span>
                    <span id="liveDurationText" class="underline">0 Jam</span>
                </div>

                <!-- Row 4: Title -->
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Judul Kegiatan / Pekerjaan *</label>
                    <input type="text" name="title" id="reportTitleInput" placeholder="Contoh: Pemeliharaan Jaringan &amp; Backup Database Server" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder:text-slate-400">
                </div>

                <!-- Row 5: Description -->
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Keterangan / Rincian Kegiatan *</label>
                    <textarea name="description" id="reportDescInput" rows="4" placeholder="Tuliskan detail tahapan pekerjaan, kendala di lapangan, solusi, hasil capaian, atau catatan penting..." required
                              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder:text-slate-400"></textarea>
                </div>

                <!-- Row 6: Supervisor / Atasan Name (Optional) -->
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Nama Atasan / Pengawas Lapangan (Opsional)</label>
                    <input type="text" name="supervisor_name" id="reportSupervisorInput" placeholder="Contoh: Bpk. Hendra Gunawan (Supervisor IT)"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder:text-slate-400">
                </div>

                <!-- Multiple Photos Upload Area -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">Foto Dokumentasi (Bisa Unggah Banyak Foto Sekaligus)</label>
                        <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">Multi-Upload Aktif</span>
                    </div>

                    <label class="flex flex-col items-center justify-center gap-2 px-4 py-6 rounded-2xl border-2 border-dashed border-slate-200 cursor-pointer transition-all hover:border-indigo-500 hover:bg-indigo-50/30"
                           id="photoDropZone">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span class="text-slate-700 text-xs font-bold" id="photoLabel">Klik untuk pilih satu atau banyak foto</span>
                        <span class="text-slate-400 text-[10px]">Tahan Ctrl / Shift untuk memilih banyak foto (JPG, PNG, WebP)</span>
                        <input type="file" name="photos[]" multiple accept="image/*" class="hidden" id="photoInput" onchange="previewReportPhotos(this)">
                    </label>

                    <!-- Preview of Existing / Newly Selected Photos -->
                    <div id="existingPhotosSection" class="mt-3 hidden">
                        <div class="text-[10px] font-extrabold uppercase text-slate-400 mb-2">Foto Saat Ini:</div>
                        <div id="existingPhotosGrid" class="grid grid-cols-3 sm:grid-cols-4 gap-2.5"></div>
                    </div>

                    <div id="newPhotosSection" class="mt-3 hidden">
                        <div class="text-[10px] font-extrabold uppercase text-slate-400 mb-2">Foto Baru Dipilih:</div>
                        <div id="newPhotosGrid" class="grid grid-cols-3 sm:grid-cols-4 gap-2.5"></div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" id="saveReportBtn" class="w-full py-3.5 rounded-xl font-extrabold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-transform active:scale-95 text-xs flex items-center justify-center gap-2">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Simpan Laporan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    // If current URL in address bar has print=1 or print_view=1, silently clean it so refresh won't print
    if (window.history && window.history.replaceState) {
        const url = new URL(window.location.href);
        if (url.searchParams.has('print') || url.searchParams.has('print_view')) {
            url.searchParams.delete('print');
            url.searchParams.delete('print_view');
            url.searchParams.delete('orient');
            window.history.replaceState({}, document.title, url.pathname + url.search);
        }
    }

    let currentExistingPhotos = [];

    window.openPrintWindow = function(url) {
        window.open(url, '_blank');
    };

    window.updateLiveDuration = function() {
        const start = document.getElementById('reportStartTimeInput').value;
        const end = document.getElementById('reportEndTimeInput').value;
        const badge = document.getElementById('liveDurationBadge');
        const text = document.getElementById('liveDurationText');

        if (start && end) {
            const sParts = start.split(':');
            const eParts = end.split(':');
            const sMins = parseInt(sParts[0], 10) * 60 + parseInt(sParts[1], 10);
            const eMins = parseInt(eParts[0], 10) * 60 + parseInt(eParts[1], 10);

            if (eMins > sMins) {
                const diff = eMins - sMins;
                const hours = Math.floor(diff / 60);
                const mins = diff % 60;
                let durationStr = '';
                if (hours > 0 && mins > 0) durationStr = `${hours} Jam ${mins} Menit`;
                else if (hours > 0) durationStr = `${hours} Jam`;
                else durationStr = `${mins} Menit`;

                text.textContent = durationStr;
                badge.classList.remove('hidden');
                badge.classList.add('flex');
                return;
            }
        }
        badge.classList.add('hidden');
        badge.classList.remove('flex');
    };

    window.openAddReportModal = function() {
        document.getElementById('reportModalTitle').textContent = 'Tambah Laporan Harian';
        document.getElementById('formActionInput').name = 'add_report';
        document.getElementById('reportIdInput').value = '';
        document.getElementById('reportTitleInput').value = '';
        document.getElementById('reportDescInput').value = '';
        document.getElementById('reportDateInput').value = '<?php echo $start_date ?: date('Y-m-d'); ?>';
        document.getElementById('reportCategoryInput').value = 'Umum';
        document.getElementById('reportProjectInput').value = '<?php echo $filter_project ?: ''; ?>';
        document.getElementById('reportStartTimeInput').value = '';
        document.getElementById('reportEndTimeInput').value = '';
        document.getElementById('reportSupervisorInput').value = '';
        document.getElementById('photoInput').value = '';
        document.getElementById('photoLabel').textContent = 'Klik untuk pilih satu atau banyak foto';
        document.getElementById('existingPhotosSection').classList.add('hidden');
        document.getElementById('newPhotosSection').classList.add('hidden');
        document.getElementById('existingPhotosGrid').innerHTML = '';
        document.getElementById('newPhotosGrid').innerHTML = '';
        document.getElementById('hiddenKeepPhotosContainer').innerHTML = '';
        currentExistingPhotos = [];
        updateLiveDuration();

        document.getElementById('saveReportBtn').innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Simpan Laporan';

        const modal = document.getElementById('reportModal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    };

    window.openEditReportModal = function(item) {
        document.getElementById('reportModalTitle').textContent = 'Edit Laporan Harian';
        document.getElementById('formActionInput').name = 'edit_report';
        document.getElementById('reportIdInput').value = item.id;
        document.getElementById('reportTitleInput').value = item.title;
        document.getElementById('reportDescInput').value = item.description;
        document.getElementById('reportDateInput').value = item.report_date;
        document.getElementById('reportCategoryInput').value = item.category || 'Umum';
        document.getElementById('reportProjectInput').value = item.project_id || '';
        document.getElementById('reportStartTimeInput').value = item.start_time ? item.start_time.substring(0, 5) : '';
        document.getElementById('reportEndTimeInput').value = item.end_time ? item.end_time.substring(0, 5) : '';
        document.getElementById('reportSupervisorInput').value = item.supervisor_name || '';
        document.getElementById('photoInput').value = '';
        document.getElementById('photoLabel').textContent = 'Tambah foto lagi (opsional)';
        document.getElementById('newPhotosSection').classList.add('hidden');
        document.getElementById('newPhotosGrid').innerHTML = '';
        updateLiveDuration();

        document.getElementById('saveReportBtn').innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Simpan Perubahan';

        currentExistingPhotos = [];
        if (item.photo_path) {
            try {
                const parsed = JSON.parse(item.photo_path);
                if (Array.isArray(parsed)) {
                    currentExistingPhotos = parsed;
                } else if (typeof item.photo_path === 'string') {
                    currentExistingPhotos = [item.photo_path];
                }
            } catch(e) {
                currentExistingPhotos = [item.photo_path];
            }
        }

        renderExistingPhotos();

        const modal = document.getElementById('reportModal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    };

    function renderExistingPhotos() {
        const grid = document.getElementById('existingPhotosGrid');
        const section = document.getElementById('existingPhotosSection');
        const hiddenCont = document.getElementById('hiddenKeepPhotosContainer');

        grid.innerHTML = '';
        hiddenCont.innerHTML = '';

        if (currentExistingPhotos.length === 0) {
            section.classList.add('hidden');
            return;
        }

        section.classList.remove('hidden');
        currentExistingPhotos.forEach((path, idx) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'keep_photos[]';
            input.value = path;
            hiddenCont.appendChild(input);

            const card = document.createElement('div');
            card.className = 'relative rounded-xl overflow-hidden border border-slate-200 bg-slate-50 h-24 group flex items-center justify-center';
            card.innerHTML = `
                <img src="serve_file.php?file=${encodeURIComponent(path.replace(/\\\\/g, '/'))}" class="w-full h-full object-cover">
                <button type="button" onclick="removeExistingPhoto(${idx})" class="absolute top-1 right-1 w-6 h-6 rounded-lg bg-rose-600 text-white flex items-center justify-center text-xs font-black shadow-sm hover:bg-rose-700 transition-colors" title="Hapus foto ini">
                    ✕
                </button>
            `;
            grid.appendChild(card);
        });
    }

    window.removeExistingPhoto = function(index) {
        currentExistingPhotos.splice(index, 1);
        renderExistingPhotos();
    };

    window.closeReportModal = function() {
        const modal = document.getElementById('reportModal');
        modal.classList.add('hidden');
        modal.style.display = 'none';
    };

    window.previewReportPhotos = function(input) {
        const grid = document.getElementById('newPhotosGrid');
        const section = document.getElementById('newPhotosSection');
        const label = document.getElementById('photoLabel');

        grid.innerHTML = '';

        if (input.files && input.files.length > 0) {
            label.textContent = '✓ ' + input.files.length + ' foto dipilih';
            section.classList.remove('hidden');

            Array.from(input.files).forEach((file, fIdx) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const card = document.createElement('div');
                    card.className = 'relative rounded-xl overflow-hidden border border-indigo-200 bg-slate-50 h-24 flex items-center justify-center';
                    card.innerHTML = `
                        <img src="${e.target.result}" class="w-full h-full object-cover">
                        <span class="absolute bottom-1 left-1 px-1.5 py-0.5 rounded bg-black/60 text-white text-[8px] font-bold">Baru #${fIdx + 1}</span>
                    `;
                    grid.appendChild(card);
                };
                reader.readAsDataURL(file);
            });
        } else {
            section.classList.add('hidden');
            label.textContent = 'Klik untuk pilih satu atau banyak foto';
        }
    };

    window.printDateGroup = function(dateStr) {
        const url = 'index.php?page=reports&start_date=' + dateStr + '&end_date=' + dateStr + '&print_view=1&orient=landscape';
        openPrintWindow(url);
    };
})();
</script>
