<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
if (!isAdmin()) {
    echo "<div class='p-8 text-center text-rose-600 font-bold'>Akses Ditolak: Halaman ini khusus Administrator.</div>";
    return;
}

// pages/team-reports.php - Executive Team Monitoring & Audit Dashboard (Khusus Administrator)
$user_id = (int)$_SESSION['user_id'];
$display_name = $_SESSION['display_name'] ?? 'Administrator';
$email = $_SESSION['email'] ?? '';
$app_name = getSetting('app_name', 'TaskFlow Pro');

// Load Company & Letterhead Settings
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

// Fetch all team users
$all_team_users = [];
try {
    $uStmt = $pdo->query("SELECT id, display_name, email, role, profile_photo FROM users ORDER BY display_name ASC");
    $all_team_users = $uStmt->fetchAll() ?: [];
} catch (\Throwable $e) {
    $all_team_users = [];
}

// Fetch all projects for filter
$projects_list = [];
try {
    $stmtProj = $pdo->query("SELECT p.id, p.name, u.display_name AS owner_name FROM projects p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.name ASC");
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
function getTeamReportPhotos($raw) {
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return array_values(array_filter($decoded));
    return [$raw];
}

function calculateTeamDurationText($start, $end) {
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

function calculateTeamDurationHoursNum($start, $end) {
    if (empty($start) || empty($end)) return 0;
    $s = strtotime("1970-01-01 $start");
    $e = strtotime("1970-01-01 $end");
    if ($e <= $s) return 0;
    return ($e - $s) / 3600;
}

// Request parameters
$view_mode       = $_GET['view'] ?? 'members'; // 'members', 'feed', 'table'
$period          = $_GET['period'] ?? 'all';
$start_date      = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : null;
$end_date        = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : null;
$filter_date     = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : null;
$filter_category = isset($_GET['category']) && $_GET['category'] !== '' ? trim($_GET['category']) : null;
$filter_project  = isset($_GET['project_id']) && (int)$_GET['project_id'] > 0 ? (int)$_GET['project_id'] : null;
$filter_user     = isset($_GET['user_id']) && (int)$_GET['user_id'] > 0 ? (int)$_GET['user_id'] : null;
$search          = isset($_GET['search']) ? trim($_GET['search']) : '';
$print_view      = isset($_GET['print_view']) && $_GET['print_view'] === '1';
$print_layout    = $_GET['layout'] ?? 'card'; // 'card' or 'table'
$print_orient    = $_GET['orient'] ?? 'landscape'; // default landscape

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

// Handle Edit Report from Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_team_report'])) {
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

        $chk = $pdo->prepare("SELECT user_id, photo_path FROM daily_reports WHERE id = ?");
        $chk->execute([$report_id]);
        $existing = $chk->fetch();

        if ($existing && $title && $description) {
            $target_report_user_id = (int)$existing['user_id'];
            $existing_photos = getTeamReportPhotos($existing['photo_path']);
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
                $upload_dir = 'uploads/' . $target_report_user_id . '/reports/';
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

            $stmt = $pdo->prepare("UPDATE daily_reports SET report_date = ?, category = ?, project_id = ?, start_time = ?, end_time = ?, supervisor_name = ?, title = ?, description = ?, photo_path = ? WHERE id = ?");
            $stmt->execute([$report_date, $category, $project_id, $start_time, $end_time, $supervisor_name, $title, $description, $photo_json, $report_id]);
            logActivity("Admin memperbarui laporan anggota: " . $title, "report", $report_id);

            $redirect_query = http_build_query(array_filter([
                'page'       => 'team-reports',
                'view'       => $view_mode,
                'success'    => 'updated',
                'period'     => $period !== 'all' ? $period : null,
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'category'   => $filter_category,
                'project_id' => $filter_project,
                'user_id'    => $filter_user
            ]));
            header("Location: index.php?" . $redirect_query);
            exit;
        }
    }
}

// Handle Delete Report from Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_team_report'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $report_id = (int)$_POST['report_id'];

        $chk = $pdo->prepare("SELECT title, photo_path FROM daily_reports WHERE id = ?");
        $chk->execute([$report_id]);
        $report = $chk->fetch();

        if ($report) {
            $photos = getTeamReportPhotos($report['photo_path']);
            foreach ($photos as $p) {
                if ($p && file_exists($p)) {
                    @unlink($p);
                }
            }
            $del = $pdo->prepare("DELETE FROM daily_reports WHERE id = ?");
            $del->execute([$report_id]);
            logActivity("Admin menghapus laporan harian anggota: " . $report['title'], "report_delete");

            $redirect_query = http_build_query(array_filter([
                'page'       => 'team-reports',
                'view'       => $view_mode,
                'success'    => 'deleted',
                'period'     => $period !== 'all' ? $period : null,
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'category'   => $filter_category,
                'project_id' => $filter_project,
                'user_id'    => $filter_user
            ]));
            header("Location: index.php?" . $redirect_query);
            exit;
        }
    }
}

// Build SQL Query for Team Reports
$sql = "SELECT r.*, p.name AS project_name, u.display_name AS author_name, u.role AS author_role, u.profile_photo AS author_photo, u.email AS author_email
        FROM daily_reports r 
        LEFT JOIN projects p ON p.id = r.project_id 
        LEFT JOIN users u ON u.id = r.user_id 
        WHERE 1=1";
$params = [];

if ($filter_user) {
    $sql .= " AND r.user_id = ?";
    $params[] = $filter_user;
}

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
    $sql .= " AND (r.title LIKE ? OR r.description LIKE ? OR r.supervisor_name LIKE ? OR p.name LIKE ? OR u.display_name LIKE ? OR u.email LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
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
    $export_user_label = "Semua_Anggota_Tim";
    if ($filter_user) {
        foreach ($all_team_users as $tu) {
            if ((int)$tu['id'] === $filter_user) {
                $export_user_label = $tu['display_name'] ?: 'User_' . $filter_user;
                break;
            }
        }
    }

    $filename = "Monitoring_Laporan_Tim_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $export_user_label) . "_" . ($start_date ? $start_date : 'All') . ($end_date && $end_date !== $start_date ? "_sd_" . $end_date : "") . ".csv";
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    $csv_headers = [
        'No',
        'Anggota Tim (Pelapor)',
        'Peran / Role',
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
    ];
    fputcsv($output, $csv_headers);

    $no = 1;
    foreach ($reports as $r) {
        $photos = getTeamReportPhotos($r['photo_path']);
        $durText = calculateTeamDurationText($r['start_time'], $r['end_time']) ?: '-';
        $startTime = !empty($r['start_time']) ? date('H:i', strtotime($r['start_time'])) : '-';
        $endTime = !empty($r['end_time']) ? date('H:i', strtotime($r['end_time'])) : '-';
        $author_name = $r['author_name'] ?: ($r['author_email'] ?: 'User #' . $r['user_id']);

        fputcsv($output, [
            $no++,
            $author_name,
            ucfirst($r['author_role'] ?? 'user'),
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

// Compute KPIs & Member Aggregations for filtered dataset
$total_reports = count($reports);
$total_photos = 0;
$total_hours = 0;
$category_counts = [];
$active_authors = [];
$grouped_by_user = [];
$grouped_by_date = [];

// Initialize member report buckets
foreach ($all_team_users as $u) {
    $grouped_by_user[$u['id']] = [
        'user'          => $u,
        'reports'       => [],
        'total_hours'   => 0,
        'total_photos'  => 0,
        'total_reports' => 0,
        'latest_report' => null
    ];
}

foreach ($reports as $r) {
    $photos = getTeamReportPhotos($r['photo_path']);
    $photo_count = count($photos);
    $dur_hours = calculateTeamDurationHoursNum($r['start_time'], $r['end_time']);
    
    $total_photos += $photo_count;
    $total_hours += $dur_hours;
    
    $cat = $r['category'] ?: 'Umum';
    $category_counts[$cat] = ($category_counts[$cat] ?? 0) + 1;
    
    $uId = (int)$r['user_id'];
    if ($uId > 0) {
        $active_authors[$uId] = true;
        if (!isset($grouped_by_user[$uId])) {
            $grouped_by_user[$uId] = [
                'user' => [
                    'id'            => $uId,
                    'display_name'  => $r['author_name'] ?: 'User #' . $uId,
                    'email'         => $r['author_email'] ?? '',
                    'role'          => $r['author_role'] ?? 'user',
                    'profile_photo' => $r['author_photo'] ?? null
                ],
                'reports'       => [],
                'total_hours'   => 0,
                'total_photos'  => 0,
                'total_reports' => 0,
                'latest_report' => null
            ];
        }
        $grouped_by_user[$uId]['reports'][] = $r;
        $grouped_by_user[$uId]['total_hours'] += $dur_hours;
        $grouped_by_user[$uId]['total_photos'] += $photo_count;
        $grouped_by_user[$uId]['total_reports']++;
        if (!$grouped_by_user[$uId]['latest_report']) {
            $grouped_by_user[$uId]['latest_report'] = $r['report_date'];
        }
    }

    $grouped_by_date[$r['report_date']][] = $r;
}

arsort($category_counts);
$top_category = !empty($category_counts) ? array_key_first($category_counts) : '-';
$total_active_members = count($active_authors);
$total_team_count = count($all_team_users);
$participation_rate = $total_team_count > 0 ? round(($total_active_members / $total_team_count) * 100) : 0;

// Sort users: active reporters first, then by total reports desc
uasort($grouped_by_user, function($a, $b) {
    if ($a['total_reports'] === $b['total_reports']) {
        return strcmp($a['user']['display_name'], $b['user']['display_name']);
    }
    return $b['total_reports'] <=> $a['total_reports'];
});

// Calculate Period Label
$period_label = 'Semua Periode';
if ($start_date && $end_date && $start_date === $end_date) {
    $period_label = date('l, d F Y', strtotime($start_date));
} elseif ($start_date && $end_date) {
    $period_label = date('d M Y', strtotime($start_date)) . ' s/d ' . date('d M Y', strtotime($end_date));
} elseif ($start_date) {
    $period_label = 'Mulai ' . date('d M Y', strtotime($start_date));
}

// Build query helper for switching views / filters
function buildTeamUrl($overrides = []) {
    $params = array_merge($_GET, $overrides);
    $params['page'] = 'team-reports';
    return 'index.php?' . http_build_query(array_filter($params, function($v) { return $v !== null && $v !== ''; }));
}

// =========================================================================
// STANDALONE PRINT VIEW FOR TEAM REPORTS
// =========================================================================
if ($print_view) {
    $doc_number = "DOC-TIM/" . date('Y/m') . "/" . ($filter_user ? str_pad((string)$filter_user, 4, '0', STR_PAD_LEFT) : 'ALL');
    $is_landscape = $print_orient === 'landscape';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Audit &amp; Rekap Laporan Tim - <?php echo htmlspecialchars($company_name); ?> (<?php echo htmlspecialchars($period_label); ?>)</title>
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
                border: none;
            }
            .btn-primary { background: #4f46e5; color: #fff; }
            .btn-primary:hover { background: #4338ca; }
            .btn-secondary { background: #e2e8f0; color: #334155; }
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
                text-decoration: none;
            }
            .btn-pill.active { background: #4f46e5; color: #fff; border-color: #4f46e5; }
            
            .header-kop {
                border-bottom: 3px double #0f172a;
                padding-bottom: 12px;
                margin-bottom: 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 20px;
            }
            .kop-brand-wrap { display: flex; align-items: center; gap: 16px; }
            .kop-logo { max-width: 90px; max-height: 70px; object-fit: contain; }
            .kop-info h1 {
                font-size: 18px;
                font-weight: 900;
                margin: 0;
                color: #0f172a;
                text-transform: uppercase;
                letter-spacing: -0.3px;
                line-height: 1.2;
            }
            .kop-info .tagline { font-size: 10.5px; font-weight: 700; color: #4f46e5; margin: 2px 0 3px; }
            .kop-info .address { font-size: 9px; color: #475569; line-height: 1.35; }
            .kop-doc-meta { text-align: right; font-size: 9px; color: #64748b; line-height: 1.4; flex-shrink: 0; }
            .kop-doc-meta .title { font-size: 11.5px; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-bottom: 2px; }

            .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 10.5px; }
            .meta-table td { padding: 6px 10px; border: 1px solid #cbd5e1; }
            .meta-table .label { background: #f8fafc; font-weight: 700; width: 20%; color: #334155; }

            .activity-card { border: 1.5px solid #cbd5e1; border-radius: 8px; margin-bottom: 16px; background: #ffffff; page-break-inside: avoid; overflow: hidden; }
            .activity-card-header { background: #f1f5f9; border-bottom: 1px solid #cbd5e1; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center; }
            .activity-card-title { font-size: 12px; font-weight: 800; color: #0f172a; }
            .activity-card-body { padding: 12px 14px; }
            .card-landscape-grid { display: grid; grid-template-columns: <?php echo $is_landscape ? '1.4fr 1fr' : '1fr'; ?>; gap: 16px; }
            .activity-description { font-size: 10px; color: #1e293b; line-height: 1.5; white-space: pre-line; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0; }
            .photo-grid-box { border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px; background: #fafafa; }
            .photo-grid-title { font-size: 9px; font-weight: 800; color: #475569; text-transform: uppercase; margin-bottom: 6px; }
            .photo-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .photo-item { text-align: center; border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; background: #ffffff; }
            .photo-img-wrap { width: 100%; min-height: 140px; max-height: 220px; background: #f8fafc; display: flex; align-items: center; justify-content: center; padding: 6px; }
            .photo-item img { max-width: 100%; max-height: 205px; width: auto; height: auto; object-fit: contain !important; display: block; margin: 0 auto; border-radius: 3px; }
            .photo-caption { font-size: 8.5px; color: #475569; padding: 4px 6px; background: #f1f5f9; font-weight: 700; border-top: 1px solid #e2e8f0; }

            .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10px; }
            .data-table th { background: #f1f5f9; border: 1px solid #94a3b8; padding: 7px 8px; text-align: left; font-weight: 800; color: #0f172a; }
            .data-table td { border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; }

            .signatures { margin-top: 32px; display: flex; justify-content: space-between; page-break-inside: avoid; }
            .sign-box { text-align: center; width: 240px; font-size: 11px; }
            .sign-space { margin-top: 55px; font-weight: 800; border-bottom: 1.5px solid #0f172a; padding-bottom: 2px; }

            @media print {
                body { background: #ffffff; padding: 0; }
                .paper { box-shadow: none; padding: 0; max-width: 100%; }
                .screen-actions { display: none !important; }
            }
        </style>
    </head>
    <body>
        <div class="screen-actions">
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 11px; font-weight: bold; color: #64748b;">Orientasi:</span>
                <a href="<?php echo buildTeamUrl(['orient' => 'landscape']); ?>" class="btn-pill <?php echo $is_landscape ? 'active' : ''; ?>">
                    🖼️ Lanskap (Horizontal)
                </a>
                <a href="<?php echo buildTeamUrl(['orient' => 'portrait']); ?>" class="btn-pill <?php echo !$is_landscape ? 'active' : ''; ?>">
                    📄 Potret (Vertikal)
                </a>
            </div>

            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 11px; font-weight: bold; color: #64748b;">Format:</span>
                <a href="<?php echo buildTeamUrl(['layout' => 'card']); ?>" class="btn-pill <?php echo $print_layout === 'card' ? 'active' : ''; ?>">
                    📑 Kartu Kegiatan
                </a>
                <a href="<?php echo buildTeamUrl(['layout' => 'table']); ?>" class="btn-pill <?php echo $print_layout === 'table' ? 'active' : ''; ?>">
                    📊 Tabel Terpadu
                </a>
            </div>

            <div style="display: flex; align-items: center; gap: 8px;">
                <a href="javascript:window.close()" class="btn btn-secondary">← Tutup</a>
                <button onclick="window.print()" class="btn btn-primary">🖨️ Simpan PDF / Cetak</button>
            </div>
        </div>

        <div class="paper">
            <div class="header-kop">
                <div class="kop-brand-wrap">
                    <?php if (!empty($logo_valid_path)): ?>
                        <img src="<?php echo htmlspecialchars($logo_valid_path); ?>?v=<?php echo time(); ?>" alt="Logo" class="kop-logo">
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
                        </div>
                    </div>
                </div>

                <div class="kop-doc-meta">
                    <div class="title">REKAP LAPORAN KERJA TIM</div>
                    <div style="font-weight: bold; color: #0f172a;">No: <?php echo $doc_number; ?></div>
                    <div>Tgl Cetak: <?php echo date('d/m/Y H:i'); ?> WIB</div>
                </div>
            </div>

            <!-- Metadata Table -->
            <table class="meta-table">
                <tr>
                    <td class="label"><?php echo $filter_user ? 'Anggota Tim' : 'Cakupan Tim'; ?></td>
                    <td style="width: 32%; font-weight: bold;">
                        <?php 
                        if ($filter_user) {
                            $selectedName = "User #$filter_user";
                            foreach ($all_team_users as $tu) {
                                if ((int)$tu['id'] === $filter_user) {
                                    $selectedName = $tu['display_name'] ?: $tu['email'];
                                    break;
                                }
                            }
                            echo htmlspecialchars($selectedName);
                        } else {
                            echo "Seluruh Anggota Tim (" . count($all_team_users) . " Terdaftar, $total_active_members Melapor)";
                        }
                        ?>
                    </td>
                    <td class="label">Total Kegiatan</td>
                    <td style="width: 26%; font-weight: bold;"><?php echo $total_reports; ?> Kegiatan Tercatat</td>
                </tr>
                <tr>
                    <td class="label">Periode Rekap</td>
                    <td style="font-weight: bold; color: #4338ca;"><?php echo htmlspecialchars($period_label); ?></td>
                    <td class="label">Akumulasi Waktu &amp; Foto</td>
                    <td><?php echo round($total_hours, 1); ?> Jam Kerja · <?php echo $total_photos; ?> Foto</td>
                </tr>
            </table>

            <?php if (empty($reports)): ?>
                <div style="text-align: center; padding: 40px; border: 1px dashed #cbd5e1; border-radius: 8px; color: #64748b;">
                    Tidak ada catatan kegiatan anggota pada kriteria ini.
                </div>
            <?php else: ?>

                <?php if ($print_layout === 'card'): ?>
                    <?php $itemNo = 1; foreach ($reports as $r): 
                        $photos = getTeamReportPhotos($r['photo_path']);
                        $photos_count = count($photos);
                        $durText = calculateTeamDurationText($r['start_time'], $r['end_time']);
                        $timeStr = !empty($r['start_time']) && !empty($r['end_time']) 
                            ? date('H:i', strtotime($r['start_time'])) . ' - ' . date('H:i', strtotime($r['end_time'])) 
                            : (!empty($r['start_time']) ? 'Mulai: ' . date('H:i', strtotime($r['start_time'])) : null);
                    ?>
                    <div class="activity-card">
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

                        <div class="activity-card-body">
                            <div class="card-landscape-grid">
                                <div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; font-size: 9px;">
                                        <span style="padding: 2px 8px; background: #0f172a; color: #ffffff; font-weight: bold; border-radius: 4px;">
                                            👤 Pelapor: <?php echo htmlspecialchars($r['author_name'] ?: 'User #' . $r['user_id']); ?><?php echo !empty($r['author_role']) ? ' (' . ucfirst($r['author_role']) . ')' : ''; ?>
                                        </span>
                                        <span style="padding: 2px 8px; background: #e0e7ff; color: #3730a3; font-weight: bold; border-radius: 4px; border: 1px solid #c7d2fe;">
                                            🏷️ <?php echo htmlspecialchars($r['category'] ?: 'Umum'); ?>
                                        </span>
                                        <?php if (!empty($r['project_name'])): ?>
                                        <span style="padding: 2px 8px; background: #f0fdf4; color: #166534; font-weight: bold; border-radius: 4px; border: 1px solid #bbf7d0;">
                                            📁 <?php echo htmlspecialchars($r['project_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['supervisor_name'])): ?>
                                        <span style="padding: 2px 8px; background: #fae8ff; color: #86198f; font-weight: bold; border-radius: 4px; border: 1px solid #f5d0fe;">
                                            👤 Pengawas: <?php echo htmlspecialchars($r['supervisor_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>

                                    <div style="font-weight: 700; font-size: 9.5px; color: #475569; margin-bottom: 3px; text-transform: uppercase;">Uraian &amp; Capaian Kerja:</div>
                                    <div class="activity-description"><?php echo htmlspecialchars($r['description']); ?></div>
                                </div>

                                <?php if (!empty($photos)): ?>
                                <div class="photo-grid-box">
                                    <div class="photo-grid-title">📸 Dokumentasi Lapangan (<?php echo $photos_count; ?> Foto):</div>
                                    <div class="photo-grid" style="<?php echo $photos_count === 1 ? 'grid-template-columns: 1fr;' : ''; ?>">
                                        <?php foreach ($photos as $pIdx => $pPath): 
                                            $pUrl = 'serve_file.php?file=' . rawurlencode(str_replace('\\', '/', $pPath));
                                        ?>
                                        <div class="photo-item">
                                            <div class="photo-img-wrap">
                                                <img src="<?php echo htmlspecialchars($pUrl); ?>" alt="Dokumentasi #<?php echo $pIdx + 1; ?>">
                                            </div>
                                            <div class="photo-caption">Foto #<?php echo $pIdx + 1; ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 25px; text-align: center;">No</th>
                                <th style="width: 85px;">Tanggal &amp; Pelapor</th>
                                <th style="width: 95px;">Kategori &amp; Proyek</th>
                                <th>Uraian &amp; Hasil Pekerjaan</th>
                                <th style="width: <?php echo $is_landscape ? '320px' : '220px'; ?>;">Foto Dokumentasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $tIndex = 1; foreach ($reports as $tr): 
                                $tPhotos = getTeamReportPhotos($tr['photo_path']);
                                $tTimeStr = !empty($tr['start_time']) && !empty($tr['end_time']) 
                                    ? date('H:i', strtotime($tr['start_time'])) . '-' . date('H:i', strtotime($tr['end_time'])) 
                                    : (!empty($tr['start_time']) ? date('H:i', strtotime($tr['start_time'])) : '-');
                            ?>
                            <tr>
                                <td style="text-align: center; font-weight: bold;"><?php echo $tIndex++; ?></td>
                                <td>
                                    <div style="font-weight: 700;"><?php echo date('d/m/Y', strtotime($tr['report_date'])); ?></div>
                                    <div style="font-size: 9px; color: #64748b;"><?php echo $tTimeStr; ?></div>
                                    <div style="font-size: 8.5px; color: #4338ca; font-weight: 800; margin-top: 3px;">👤 <?php echo htmlspecialchars($tr['author_name'] ?: 'User #' . $tr['user_id']); ?></div>
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

            <div class="signatures">
                <div class="sign-box">
                    <div>Penanggung Jawab / Tim,</div>
                    <div class="sign-space">
                        <?php echo $filter_user ? htmlspecialchars($selectedName) : "Seluruh Tim Pelaksana"; ?>
                    </div>
                    <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">Pelaksana Kerja</div>
                </div>

                <div class="sign-box">
                    <div>Diperiksa &amp; Disetujui Oleh,</div>
                    <div class="sign-space"><?php echo htmlspecialchars($display_name); ?></div>
                    <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">Administrator / Supervisor</div>
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

    <!-- 1. EXECUTIVE COMMAND HEADER (Distinct Dark Slate Glassmorphic Theme) -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 p-6 md:p-8 text-white border border-slate-800/80 shadow-2xl">
        <!-- Background Ambient Accents -->
        <div class="absolute -right-16 -top-16 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-1/3 -bottom-20 w-72 h-72 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
        
        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <!-- Left Info Block -->
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 text-xs font-black tracking-wide">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    AUDIT &amp; PENGAWASAN TIM · KHUSUS ADMINISTRATOR
                </div>
                
                <div>
                    <h1 class="text-2xl md:text-3xl font-black tracking-tight text-white flex items-center gap-3">
                        <span>Pusat Kendali Laporan Tim</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-lg bg-indigo-600 text-white font-extrabold tracking-normal">Live</span>
                    </h1>
                    <p class="text-slate-300 text-sm max-w-2xl mt-1 leading-relaxed">
                        Dasbor monitoring terpadu seluruh personil tim. Evaluasi kepatuhan pelaporan, rincian aktivitas di lapangan, akumulasi jam kerja, dan verifikasi dokumentasi foto real-time.
                    </p>
                </div>

                <!-- Live Team Participation Bar -->
                <div class="pt-1 max-w-md">
                    <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                        <span class="text-slate-300">Tingkat Partisipasi Tim (<?php echo htmlspecialchars($period_label); ?>)</span>
                        <span class="text-indigo-300 font-extrabold"><?php echo $participation_rate; ?>% (<?php echo $total_active_members; ?>/<?php echo $total_team_count; ?> Anggota)</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-slate-800 border border-slate-700 overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-blue-500 via-indigo-500 to-emerald-400 rounded-full transition-all duration-500" style="width: <?php echo min(100, $participation_rate); ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Right Action Toolbar -->
            <div class="flex flex-wrap items-center gap-2.5 flex-shrink-0">
                <a href="<?php echo route('reports'); ?>" class="px-4 py-2.5 rounded-xl bg-slate-800/90 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs font-bold transition-all flex items-center gap-2 shadow-sm active:scale-95">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Laporan Pribadi Saya</span>
                </a>

                <?php if (!empty($reports)): ?>
                <!-- Export Excel Button -->
                <a href="<?php echo buildTeamUrl(['export' => 'csv']); ?>" class="px-4 py-2.5 rounded-xl bg-emerald-600/90 hover:bg-emerald-600 border border-emerald-500/30 text-white text-xs font-extrabold transition-all flex items-center gap-2 shadow-sm active:scale-95">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Ekspor Excel</span>
                </a>

                <!-- Dedicated Print Button -->
                <button type="button" onclick="openTeamPrintWindow('<?php echo buildTeamUrl(['print_view' => '1', 'orient' => 'landscape']); ?>')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 border border-indigo-400/30 text-white text-xs font-black transition-all flex items-center gap-2 shadow-lg shadow-indigo-900/40 active:scale-95">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    <span>Cetak Rekap A4</span>
                </button>
                <?php endif; ?>
            </div>
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
            if($_GET['success'] === 'updated') echo "Laporan anggota tim berhasil diperbarui oleh Administrator."; 
            if($_GET['success'] === 'deleted') echo "Laporan anggota tim telah dihapus oleh Administrator."; 
            ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- 2. EXECUTIVE TEAM MEMBER QUICK-SELECTOR BAR (Avatars Carousel) -->
    <div class="bg-white rounded-3xl border border-slate-200 p-4 md:p-5 shadow-sm space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                    <span>👥</span> Pilih Personil Tim
                </span>
                <span class="text-[11px] text-slate-400 font-bold">(<?php echo count($all_team_users); ?> Anggota)</span>
            </div>
            <?php if ($filter_user): ?>
            <a href="<?php echo buildTeamUrl(['user_id' => '']); ?>" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                ✕ Reset Filter Anggota
            </a>
            <?php endif; ?>
        </div>

        <!-- Horizontal scrollable list of team members with submission badges -->
        <div class="flex items-center gap-3 overflow-x-auto pb-2 custom-scrollbar">
            <!-- 'All' Card -->
            <a href="<?php echo buildTeamUrl(['user_id' => '']); ?>" 
               class="flex items-center gap-3 px-4 py-2.5 rounded-2xl border transition-all flex-shrink-0 <?php echo !$filter_user ? 'bg-slate-900 text-white border-slate-900 shadow-md ring-2 ring-slate-900/20' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-200'; ?>">
                <div class="w-9 h-9 rounded-xl <?php echo !$filter_user ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-600'; ?> flex items-center justify-center font-black text-sm">
                    👥
                </div>
                <div class="text-left pr-1">
                    <div class="text-xs font-black leading-none">Semua Tim</div>
                    <div class="text-[10px] <?php echo !$filter_user ? 'text-slate-300' : 'text-slate-500'; ?> font-bold mt-1">
                        <?php echo $total_reports; ?> Laporan
                    </div>
                </div>
            </a>

            <!-- Individual Member Cards -->
            <?php foreach ($grouped_by_user as $uId => $uData): 
                $usr = $uData['user'];
                $is_selected = ($filter_user === (int)$uId);
                $has_submitted = $uData['total_reports'] > 0;
                $avatar_url = !empty($usr['profile_photo']) && file_exists(__DIR__ . '/../uploads/avatars/' . $usr['profile_photo'])
                    ? 'uploads/avatars/' . $usr['profile_photo']
                    : getAvatarDataUri($usr['display_name'] ?: 'User', 36);
            ?>
            <a href="<?php echo buildTeamUrl(['user_id' => $uId]); ?>" 
               class="flex items-center gap-3 px-3.5 py-2 rounded-2xl border transition-all flex-shrink-0 group <?php echo $is_selected ? 'bg-indigo-600 text-white border-indigo-600 shadow-md ring-2 ring-indigo-500/30' : 'bg-slate-50 hover:bg-slate-100 text-slate-800 border-slate-200'; ?>">
                <div class="relative flex-shrink-0">
                    <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="w-9 h-9 rounded-xl object-cover border <?php echo $is_selected ? 'border-white/30' : 'border-slate-300'; ?>">
                    <span class="absolute -bottom-1 -right-1 w-3.5 h-3.5 rounded-full border-2 <?php echo $is_selected ? 'border-indigo-600' : 'border-white'; ?> <?php echo $has_submitted ? 'bg-emerald-500' : 'bg-slate-300'; ?>" title="<?php echo $has_submitted ? 'Ada laporan' : 'Belum melapor'; ?>"></span>
                </div>
                <div class="text-left pr-1">
                    <div class="text-xs font-extrabold leading-tight truncate max-w-[120px] <?php echo $is_selected ? 'text-white' : 'text-slate-900 group-hover:text-indigo-600'; ?>">
                        <?php echo htmlspecialchars($usr['display_name'] ?: 'User #' . $uId); ?>
                    </div>
                    <div class="flex items-center gap-1.5 text-[10px] font-bold mt-0.5 <?php echo $is_selected ? 'text-indigo-100' : ($has_submitted ? 'text-emerald-600' : 'text-slate-400'); ?>">
                        <span><?php echo $uData['total_reports']; ?> Lap</span>
                        <span>·</span>
                        <span><?php echo round($uData['total_hours'], 1); ?>j</span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 3. FOUR EXECUTIVE KPI METRIC CARDS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1 -->
        <div class="bg-gradient-to-br from-white to-indigo-50/40 rounded-3xl p-5 border border-indigo-100 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-black text-indigo-900/70 uppercase tracking-wider">Total Laporan Tim</span>
                <span class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm">📋</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-900"><?php echo $total_reports; ?></span>
                <span class="text-xs font-bold text-slate-500">kegiatan</span>
            </div>
            <div class="text-[11px] font-semibold text-slate-500 mt-1">
                Kategori teratas: <strong class="text-indigo-700"><?php echo htmlspecialchars($top_category); ?></strong>
            </div>
        </div>

        <!-- Card 2 -->
        <div class="bg-gradient-to-br from-white to-blue-50/40 rounded-3xl p-5 border border-blue-100 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-black text-blue-900/70 uppercase tracking-wider">Anggota Melapor</span>
                <span class="w-8 h-8 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm">👥</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-900"><?php echo $total_active_members; ?></span>
                <span class="text-xs font-bold text-slate-500">/ <?php echo $total_team_count; ?> personil</span>
            </div>
            <div class="text-[11px] font-semibold text-blue-700 mt-1 flex items-center gap-1">
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                <strong><?php echo $participation_rate; ?>%</strong> partisipasi aktif
            </div>
        </div>

        <!-- Card 3 -->
        <div class="bg-gradient-to-br from-white to-emerald-50/40 rounded-3xl p-5 border border-emerald-100 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-black text-emerald-900/70 uppercase tracking-wider">Jam Lapangan Tim</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-sm">⏱️</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-900"><?php echo round($total_hours, 1); ?></span>
                <span class="text-xs font-bold text-slate-500">jam kerja</span>
            </div>
            <div class="text-[11px] font-semibold text-slate-500 mt-1">
                Rata-rata: <strong class="text-emerald-700"><?php echo $total_active_members > 0 ? round($total_hours / $total_active_members, 1) : 0; ?> jam/orang</strong>
            </div>
        </div>

        <!-- Card 4 -->
        <div class="bg-gradient-to-br from-white to-purple-50/40 rounded-3xl p-5 border border-purple-100 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-black text-purple-900/70 uppercase tracking-wider">Bukti Dokumentasi</span>
                <span class="w-8 h-8 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-sm">📸</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-900"><?php echo $total_photos; ?></span>
                <span class="text-xs font-bold text-slate-500">foto terverifikasi</span>
            </div>
            <div class="text-[11px] font-semibold text-purple-700 mt-1">
                Bukti visual seluruh lapangan
            </div>
        </div>
    </div>

    <!-- 4. VIEW MODE SWITCHER & ADVANCED AUDIT FILTER BAR -->
    <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm space-y-4">
        
        <!-- Header row: View Mode Tabs + Period Filter Pills -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            
            <!-- VIEW MODE TABS -->
            <div class="flex items-center p-1 rounded-2xl bg-slate-100/90 border border-slate-200 flex-shrink-0">
                <a href="<?php echo buildTeamUrl(['view' => 'members']); ?>"
                   class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-black transition-all <?php echo $view_mode === 'members' ? 'bg-white text-indigo-700 shadow-sm border border-slate-200' : 'text-slate-600 hover:text-slate-900'; ?>">
                    <span>👥</span>
                    <span>Per Anggota Tim</span>
                </a>

                <a href="<?php echo buildTeamUrl(['view' => 'table']); ?>"
                   class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-black transition-all <?php echo $view_mode === 'table' ? 'bg-white text-indigo-700 shadow-sm border border-slate-200' : 'text-slate-600 hover:text-slate-900'; ?>">
                    <span>📊</span>
                    <span>Tabel Audit</span>
                </a>

                <a href="<?php echo buildTeamUrl(['view' => 'feed']); ?>"
                   class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-black transition-all <?php echo $view_mode === 'feed' ? 'bg-white text-indigo-700 shadow-sm border border-slate-200' : 'text-slate-600 hover:text-slate-900'; ?>">
                    <span>📑</span>
                    <span>Feed Kronologis</span>
                </a>
            </div>

            <!-- Quick Period Filter Pills -->
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider mr-1">Periode:</span>
                
                <a href="<?php echo buildTeamUrl(['period' => 'all', 'date' => '', 'start_date' => '', 'end_date' => '']); ?>"
                   class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?php echo $period === 'all' && !$filter_date && !$start_date ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                    Semua
                </a>

                <a href="<?php echo buildTeamUrl(['period' => 'today', 'date' => '', 'start_date' => '', 'end_date' => '']); ?>"
                   class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?php echo $period === 'today' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                    Hari Ini
                </a>

                <a href="<?php echo buildTeamUrl(['period' => 'this_week', 'date' => '', 'start_date' => '', 'end_date' => '']); ?>"
                   class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?php echo $period === 'this_week' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                    Minggu Ini
                </a>

                <a href="<?php echo buildTeamUrl(['period' => 'this_month', 'date' => '', 'start_date' => '', 'end_date' => '']); ?>"
                   class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?php echo $period === 'this_month' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                    Bulan Ini
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-3 items-end">
            <input type="hidden" name="page" value="team-reports">
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
            <input type="hidden" name="period" value="custom">

            <!-- Team Member Dropdown -->
            <div>
                <label for="teamUserFilter" class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">👤 Anggota Tim</label>
                <select id="teamUserFilter" name="user_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">👥 Semua Anggota (<?php echo count($all_team_users); ?>)</option>
                    <?php foreach ($all_team_users as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $filter_user === (int)$u['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['display_name'] ?: $u['email']); ?> (<?php echo htmlspecialchars(ucfirst($u['role'])); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

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
                <label for="projectFilterInput" class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Proyek</label>
                <select id="projectFilterInput" name="project_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Semua Proyek</option>
                    <?php foreach ($projects_list as $prj): ?>
                    <option value="<?php echo $prj['id']; ?>" <?php echo $filter_project === (int)$prj['id'] ? 'selected' : ''; ?>>
                        📁 <?php echo htmlspecialchars($prj['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Search Keyword -->
            <div>
                <label for="searchInput" class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Cari Kata Kunci</label>
                <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Judul, uraian, pengawas..."
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder:text-slate-400">
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2.5 rounded-xl text-xs font-black text-white bg-slate-900 hover:bg-slate-800 transition-colors text-center shadow-sm">
                    Terapkan
                </button>
                <?php if ($start_date || $end_date || $filter_category || $filter_project || $filter_user || $search): ?>
                    <a href="<?php echo buildTeamUrl(['user_id' => '', 'category' => '', 'project_id' => '', 'search' => '', 'start_date' => '', 'end_date' => '', 'period' => 'all']); ?>" 
                       class="px-3 py-2.5 rounded-xl text-xs font-bold text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 transition-colors" title="Reset Filter">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- 5. MAIN CONTENT AREA (Based on active View Mode) -->

    <?php if (empty($reports)): ?>
        <div class="bg-white rounded-3xl border border-slate-200 p-16 text-center shadow-sm">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 bg-indigo-50 text-indigo-600 text-3xl">
                👥
            </div>
            <h3 class="text-slate-800 font-extrabold text-base mb-1">Tidak Ada Laporan Anggota Ditemukan</h3>
            <p class="text-slate-400 text-xs max-w-sm mx-auto mb-6">Belum ada personil tim yang memasukkan kegiatan pada kriteria filter yang dipilih.</p>
            <a href="<?php echo buildTeamUrl(['user_id' => '', 'category' => '', 'project_id' => '', 'search' => '', 'period' => 'all']); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold shadow-sm">
                Lihat Semua Laporan Tim
            </a>
        </div>
    <?php else: ?>

        <!-- ========================================================================= -->
        <!-- VIEW MODE 1: PER ANGGOTA TIM (Grouped By Team Member)                     -->
        <!-- ========================================================================= -->
        <?php if ($view_mode === 'members'): ?>
            <div class="space-y-8">
                <?php 
                foreach ($grouped_by_user as $uId => $uData): 
                    if ($filter_user && (int)$uId !== $filter_user) continue;
                    if (empty($uData['reports'])) continue; // Skip members with 0 reports in current filter

                    $usr = $uData['user'];
                    $usr_avatar = !empty($usr['profile_photo']) && file_exists(__DIR__ . '/../uploads/avatars/' . $usr['profile_photo'])
                        ? 'uploads/avatars/' . $usr['profile_photo']
                        : getAvatarDataUri($usr['display_name'] ?: 'User', 48);
                ?>
                <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                    
                    <!-- Member Header Banner -->
                    <div class="p-5 md:p-6 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800">
                        <div class="flex items-center gap-4">
                            <div class="relative">
                                <img src="<?php echo $usr_avatar; ?>" alt="Avatar" class="w-13 h-13 rounded-2xl object-cover border-2 border-indigo-400/50 shadow-md">
                                <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-emerald-500 border-2 border-slate-900" title="Aktif Melapor"></span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-base md:text-lg font-black text-white">
                                        <?php echo htmlspecialchars($usr['display_name'] ?: 'User #' . $uId); ?>
                                    </h2>
                                    <span class="px-2 py-0.5 rounded-md bg-indigo-500/30 text-indigo-300 text-[10px] font-extrabold uppercase border border-indigo-400/30">
                                        <?php echo htmlspecialchars($usr['role'] ?? 'user'); ?>
                                    </span>
                                </div>
                                <div class="text-xs text-slate-300 font-medium mt-0.5">
                                    <?php echo htmlspecialchars($usr['email'] ?? ''); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Member Quick Stat Pills -->
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="px-3 py-1.5 rounded-xl bg-white/10 backdrop-blur-sm border border-white/10 text-xs font-bold text-slate-200 flex items-center gap-1.5">
                                <span>📋</span>
                                <span><strong><?php echo $uData['total_reports']; ?></strong> Kegiatan</span>
                            </div>
                            <div class="px-3 py-1.5 rounded-xl bg-white/10 backdrop-blur-sm border border-white/10 text-xs font-bold text-emerald-300 flex items-center gap-1.5">
                                <span>⏱️</span>
                                <span><strong><?php echo round($uData['total_hours'], 1); ?></strong> Jam Kerja</span>
                            </div>
                            <div class="px-3 py-1.5 rounded-xl bg-white/10 backdrop-blur-sm border border-white/10 text-xs font-bold text-purple-300 flex items-center gap-1.5">
                                <span>📸</span>
                                <span><strong><?php echo $uData['total_photos']; ?></strong> Foto</span>
                            </div>
                            <button type="button" onclick="openTeamPrintWindow('<?php echo buildTeamUrl(['user_id' => $uId, 'print_view' => '1', 'orient' => 'landscape']); ?>')"
                                    class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm ml-1">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                <span>Cetak Anggota Ini</span>
                            </button>
                        </div>
                    </div>

                    <!-- Member's Reports Grid / List -->
                    <div class="divide-y divide-slate-100 bg-slate-50/30">
                        <?php foreach ($uData['reports'] as $item): 
                            $photos = getTeamReportPhotos($item['photo_path']);
                            $photos_count = count($photos);
                            $item_cat = $item['category'] ?: 'Umum';
                            $cat_style = $categories_meta[$item_cat] ?? $categories_meta['Umum'];
                            $dur_text = calculateTeamDurationText($item['start_time'], $item['end_time']);
                        ?>
                        <div class="p-5 md:p-6 bg-white hover:bg-slate-50/60 transition-colors">
                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                                
                                <!-- Activity Core Details -->
                                <div class="space-y-3 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <!-- Date Badge -->
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-extrabold bg-slate-900 text-white">
                                            📅 <?php echo date('d M Y', strtotime($item['report_date'])); ?>
                                        </span>

                                        <!-- Category -->
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold border <?php echo $cat_style['color']; ?>">
                                            <span><?php echo $cat_style['icon']; ?></span>
                                            <span><?php echo htmlspecialchars($item_cat); ?></span>
                                        </span>

                                        <!-- Project -->
                                        <?php if (!empty($item['project_name'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            📁 <?php echo htmlspecialchars($item['project_name']); ?>
                                        </span>
                                        <?php endif; ?>

                                        <!-- Time & Duration -->
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

                                        <!-- Supervisor -->
                                        <?php if (!empty($item['supervisor_name'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                            👤 Pengawas: <?php echo htmlspecialchars($item['supervisor_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <h3 class="text-base font-extrabold text-slate-900 leading-snug">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </h3>
                                        <div class="text-[11px] font-bold text-slate-400 mt-0.5">
                                            Dicatat: <?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?> WIB
                                        </div>
                                    </div>

                                    <!-- Description box -->
                                    <div class="text-xs text-slate-700 font-medium leading-relaxed whitespace-pre-line bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
                                        <?php echo htmlspecialchars($item['description']); ?>
                                    </div>

                                    <!-- Photos -->
                                    <?php if ($photos_count > 0): ?>
                                    <div>
                                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-2">Dokumentasi Terlampir (<?php echo $photos_count; ?> Foto):</div>
                                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                            <?php foreach ($photos as $pIndex => $pPath): 
                                                $pUrl = 'serve_file.php?file=' . rawurlencode(str_replace('\\', '/', $pPath));
                                            ?>
                                            <div class="group/img relative h-28 rounded-2xl overflow-hidden bg-slate-100 border border-slate-200 cursor-pointer shadow-sm flex items-center justify-center"
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

                                <!-- Action Buttons for Admin -->
                                <div class="flex md:flex-col items-center gap-2 flex-shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-slate-100">
                                    <button type="button" onclick='openEditTeamReportModal(<?php echo json_encode($item); ?>)'
                                            title="Edit / Koreksi Laporan Anggota" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 transition-colors flex items-center gap-1.5 text-xs font-bold">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        <span>Edit</span>
                                    </button>

                                    <form method="POST" onsubmit="return confirm('Hapus laporan kegiatan anggota ini?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="delete_team_report" value="1">
                                        <input type="hidden" name="report_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" title="Hapus Laporan" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-rose-50 text-slate-500 hover:text-rose-600 transition-colors flex items-center gap-1.5 text-xs font-bold">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            <span>Hapus</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <!-- ========================================================================= -->
        <!-- VIEW MODE 2: TABEL AUDIT & VERIFIKASI (Data Grid Matrix)                  -->
        <!-- ========================================================================= -->
        <?php elseif ($view_mode === 'table'): ?>
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black tracking-wide">Tabel Audit Kegiatan Tim</h2>
                        <p class="text-xs text-slate-400">Total <?php echo $total_reports; ?> laporan tercatat dalam filter aktif</p>
                    </div>
                    <span class="text-xs font-extrabold px-3 py-1 rounded-xl bg-indigo-600 text-white">
                        <?php echo htmlspecialchars($period_label); ?>
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100/90 text-slate-700 font-extrabold uppercase tracking-wider text-[10px] border-b border-slate-200">
                                <th class="p-3.5 text-center w-10">No</th>
                                <th class="p-3.5">Anggota Tim (Pelapor)</th>
                                <th class="p-3.5">Tgl &amp; Waktu</th>
                                <th class="p-3.5">Kategori &amp; Proyek</th>
                                <th class="p-3.5">Judul &amp; Ringkasan Kegiatan</th>
                                <th class="p-3.5 text-center">Foto</th>
                                <th class="p-3.5 text-center">Pengawas</th>
                                <th class="p-3.5 text-right w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                            <?php $tIndex = 1; foreach ($reports as $r): 
                                $photos = getTeamReportPhotos($r['photo_path']);
                                $photos_count = count($photos);
                                $item_cat = $r['category'] ?: 'Umum';
                                $cat_style = $categories_meta[$item_cat] ?? $categories_meta['Umum'];
                                $dur_text = calculateTeamDurationText($r['start_time'], $r['end_time']);
                                $avatar_url = !empty($r['author_photo']) && file_exists(__DIR__ . '/../uploads/avatars/' . $r['author_photo'])
                                    ? 'uploads/avatars/' . $r['author_photo']
                                    : getAvatarDataUri($r['author_name'] ?: 'User', 28);
                            ?>
                            <tr class="hover:bg-indigo-50/30 transition-colors">
                                <td class="p-3.5 text-center font-black text-slate-400">
                                    <?php echo $tIndex++; ?>
                                </td>
                                
                                <td class="p-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="w-7 h-7 rounded-lg object-cover border border-slate-200">
                                        <div>
                                            <div class="font-extrabold text-slate-900"><?php echo htmlspecialchars($r['author_name'] ?: 'User #' . $r['user_id']); ?></div>
                                            <div class="text-[10px] text-slate-400 font-bold uppercase"><?php echo htmlspecialchars($r['author_role'] ?? 'user'); ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-3.5 whitespace-nowrap">
                                    <div class="font-black text-slate-900"><?php echo date('d/m/Y', strtotime($r['report_date'])); ?></div>
                                    <div class="text-[10px] text-slate-500 font-bold">
                                        <?php if (!empty($r['start_time']) && !empty($r['end_time'])): ?>
                                            ⏱️ <?php echo date('H:i', strtotime($r['start_time'])); ?>-<?php echo date('H:i', strtotime($r['end_time'])); ?>
                                            <?php if ($dur_text): ?><span class="text-indigo-600">(<?php echo $dur_text; ?>)</span><?php endif; ?>
                                        <?php elseif (!empty($r['start_time'])): ?>
                                            ⏱️ <?php echo date('H:i', strtotime($r['start_time'])); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="p-3.5">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold border <?php echo $cat_style['color']; ?>">
                                        <?php echo $cat_style['icon'] . ' ' . htmlspecialchars($item_cat); ?>
                                    </span>
                                    <?php if (!empty($r['project_name'])): ?>
                                        <div class="text-[10px] text-emerald-700 font-bold mt-1 truncate max-w-[140px]" title="<?php echo htmlspecialchars($r['project_name']); ?>">
                                            📁 <?php echo htmlspecialchars($r['project_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="p-3.5 max-w-xs">
                                    <div class="font-black text-slate-900 leading-tight mb-1"><?php echo htmlspecialchars($r['title']); ?></div>
                                    <div class="text-[11px] text-slate-600 line-clamp-2 leading-relaxed"><?php echo htmlspecialchars($r['description']); ?></div>
                                </td>

                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <?php if ($photos_count > 0): ?>
                                        <button type="button" onclick='openEditTeamReportModal(<?php echo json_encode($r); ?>)' class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-purple-50 hover:bg-purple-100 text-purple-700 font-black text-[11px] border border-purple-200 transition-colors">
                                            📸 <?php echo $photos_count; ?> Foto
                                        </button>
                                    <?php else: ?>
                                        <span class="text-slate-300 text-[10px] italic">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-3.5 text-center text-[11px] text-slate-600 whitespace-nowrap">
                                    <?php echo !empty($r['supervisor_name']) ? htmlspecialchars($r['supervisor_name']) : '<span class="text-slate-300">-</span>'; ?>
                                </td>

                                <td class="p-3.5 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1">
                                        <button type="button" onclick='openEditTeamReportModal(<?php echo json_encode($r); ?>)'
                                                title="Edit Laporan" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 transition-colors flex items-center justify-center">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>

                                        <form method="POST" onsubmit="return confirm('Hapus laporan kegiatan anggota ini?')" class="inline-block">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="delete_team_report" value="1">
                                            <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                                            <button type="submit" title="Hapus Laporan" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-50 text-slate-400 hover:text-rose-600 transition-colors flex items-center justify-center">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- ========================================================================= -->
        <!-- VIEW MODE 3: FEED KRONOLOGIS TIM (Executive Stream By Date)               -->
        <!-- ========================================================================= -->
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($grouped_by_date as $date_group => $day_items): 
                    $formatted_date_group = date('l, d F Y', strtotime($date_group));
                    $day_hours = 0;
                    $day_photos = 0;
                    $day_authors = [];
                    foreach ($day_items as $di) {
                        $day_hours += calculateTeamDurationHoursNum($di['start_time'], $di['end_time']);
                        $day_photos += count(getTeamReportPhotos($di['photo_path']));
                        if (!empty($di['user_id'])) {
                            $day_authors[$di['user_id']] = true;
                        }
                    }
                ?>
                <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                    <!-- Date Group Header -->
                    <div class="px-6 py-4 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex flex-col items-center justify-center font-black leading-none shadow-md">
                                <span class="text-xs"><?php echo date('d', strtotime($date_group)); ?></span>
                                <span class="text-[8px] uppercase"><?php echo date('M', strtotime($date_group)); ?></span>
                            </div>
                            <div>
                                <h2 class="text-sm font-black text-white"><?php echo $formatted_date_group; ?></h2>
                                <div class="flex items-center gap-2 text-[10px] font-bold text-slate-300 uppercase tracking-wider">
                                    <span><?php echo count($day_items); ?> Kegiatan</span>
                                    <span>·</span>
                                    <span class="text-blue-300">👥 <?php echo count($day_authors); ?> Anggota</span>
                                    <?php if ($day_hours > 0): ?>
                                    <span>·</span>
                                    <span class="text-emerald-300">⏱️ <?php echo round($day_hours, 1); ?> Jam</span>
                                    <?php endif; ?>
                                    <?php if ($day_photos > 0): ?>
                                    <span>·</span>
                                    <span class="text-purple-300">📸 <?php echo $day_photos; ?> Foto</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" onclick="printTeamDateGroup('<?php echo $date_group; ?>')" class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-indigo-600 text-slate-200 hover:text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm border border-slate-700">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            <span>Cetak Tanggal Ini</span>
                        </button>
                    </div>

                    <!-- Items in Day -->
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($day_items as $item): 
                            $photos = getTeamReportPhotos($item['photo_path']);
                            $photos_count = count($photos);
                            $item_cat = $item['category'] ?: 'Umum';
                            $cat_style = $categories_meta[$item_cat] ?? $categories_meta['Umum'];
                            $dur_text = calculateTeamDurationText($item['start_time'], $item['end_time']);
                            $author_avatar_src = !empty($item['author_photo']) && file_exists(__DIR__ . '/../uploads/avatars/' . $item['author_photo'])
                                ? 'uploads/avatars/' . $item['author_photo']
                                : getAvatarDataUri($item['author_name'] ?: 'User', 36);
                        ?>
                        <div class="p-6 hover:bg-slate-50/40 transition-colors flex flex-col gap-4">
                            
                            <!-- Top Row: Title, Author, Category Badge, Time Badge & Actions -->
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <!-- Author Badge -->
                                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-xl text-xs font-black bg-slate-900 text-white shadow-sm">
                                            <img src="<?php echo $author_avatar_src; ?>" alt="Avatar" class="w-4 h-4 rounded-full object-cover">
                                            <span><?php echo htmlspecialchars($item['author_name'] ?: ($item['author_email'] ?: 'User #' . $item['user_id'])); ?></span>
                                            <span class="text-[9px] uppercase tracking-wider px-1.5 py-0.2 rounded bg-indigo-500/30 text-indigo-200 font-extrabold"><?php echo htmlspecialchars($item['author_role'] ?? 'user'); ?></span>
                                        </div>

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
                                        <span>Dicatat <?php echo date('H:i, d M Y', strtotime($item['created_at'])); ?> WIB</span>
                                        <?php if ($photos_count > 0): ?>
                                        <span>·</span>
                                        <span class="text-indigo-600 font-extrabold bg-indigo-50 px-1.5 py-0.5 rounded">📸 <?php echo $photos_count; ?> Foto Dokumentasi</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Action Buttons for Admin -->
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <button type="button" onclick='openEditTeamReportModal(<?php echo json_encode($item); ?>)'
                                            title="Edit / Koreksi Laporan Anggota" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 transition-colors flex items-center justify-center">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>

                                    <form method="POST" onsubmit="return confirm('Hapus laporan kegiatan anggota ini?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="delete_team_report" value="1">
                                        <input type="hidden" name="report_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" title="Hapus Laporan" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-rose-50 text-slate-400 hover:text-rose-600 transition-colors flex items-center justify-center">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Description text -->
                            <div class="text-xs text-slate-700 font-medium leading-relaxed whitespace-pre-line bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                <?php echo htmlspecialchars($item['description']); ?>
                            </div>

                            <!-- Photos Gallery -->
                            <?php if ($photos_count > 0): ?>
                            <div>
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-2">Dokumentasi Terlampir (<?php echo $photos_count; ?> Foto):</div>
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

    <?php endif; ?>

</div>

<!-- Admin Edit Team Report Modal -->
<div id="teamReportModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-black/60 backdrop-blur-sm" role="dialog" aria-modal="true">
    <div class="bg-white rounded-3xl max-w-2xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900" id="teamReportModalTitle">Edit Laporan Anggota Tim</h3>
                    <p class="text-xs text-slate-400 font-medium">Koreksi data kegiatan dan dokumentasi anggota tim</p>
                </div>
            </div>
            <button type="button" onclick="closeTeamReportModal()" class="w-8 h-8 rounded-xl hover:bg-slate-200 transition-colors flex items-center justify-center text-slate-500">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <form method="POST" enctype="multipart/form-data" id="teamReportForm" data-no-ajax="true" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="edit_team_report" value="1">
                <input type="hidden" name="report_id" id="teamReportIdInput" value="">
                <div id="teamKeepPhotosContainer"></div>

                <!-- Row 1: Date & Category -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Tanggal Kegiatan *</label>
                        <input type="date" name="report_date" id="teamReportDateInput" required
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Kategori Kegiatan *</label>
                        <select name="category" id="teamReportCategoryInput" required
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <?php foreach ($categories_meta as $catKey => $catObj): ?>
                            <option value="<?php echo htmlspecialchars($catKey); ?>">
                                <?php echo $catObj['icon'] . ' ' . htmlspecialchars($catKey); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2: Linked Project -->
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Tautkan ke Proyek</label>
                    <select name="project_id" id="teamReportProjectInput"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Tanpa Proyek Terkait (Umum) --</option>
                        <?php foreach ($projects_list as $prj): ?>
                        <option value="<?php echo $prj['id']; ?>">
                            📁 <?php echo htmlspecialchars($prj['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Row 3: Time Start, End -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Jam Mulai</label>
                        <input type="time" name="start_time" id="teamReportStartTimeInput"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Jam Selesai</label>
                        <input type="time" name="end_time" id="teamReportEndTimeInput"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Row 4: Title -->
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Judul Kegiatan *</label>
                    <input type="text" name="title" id="teamReportTitleInput" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Row 5: Description -->
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Rincian Kegiatan *</label>
                    <textarea name="description" id="teamReportDescInput" rows="4" required
                              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <!-- Row 6: Supervisor -->
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Nama Pengawas / Atasan</label>
                    <input type="text" name="supervisor_name" id="teamReportSupervisorInput"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Photo Management -->
                <div>
                    <div id="teamExistingPhotosSection" class="mt-2 hidden">
                        <div class="text-[10px] font-extrabold uppercase text-slate-400 mb-2">Foto Saat Ini (Klik tanda ✕ untuk menghapus):</div>
                        <div id="teamExistingPhotosGrid" class="grid grid-cols-3 sm:grid-cols-4 gap-2.5"></div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3.5 rounded-xl font-extrabold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-transform active:scale-95 text-xs flex items-center justify-center gap-2">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Simpan Perubahan Laporan Anggota
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    let teamExistingPhotos = [];

    window.openTeamPrintWindow = function(url) {
        window.open(url, '_blank');
    };

    window.printTeamDateGroup = function(dateStr) {
        const url = 'index.php?page=team-reports&start_date=' + dateStr + '&end_date=' + dateStr + '&print_view=1&orient=landscape';
        openTeamPrintWindow(url);
    };

    window.openEditTeamReportModal = function(item) {
        document.getElementById('teamReportIdInput').value = item.id;
        document.getElementById('teamReportTitleInput').value = item.title;
        document.getElementById('teamReportDescInput').value = item.description;
        document.getElementById('teamReportDateInput').value = item.report_date;
        document.getElementById('teamReportCategoryInput').value = item.category || 'Umum';
        document.getElementById('teamReportProjectInput').value = item.project_id || '';
        document.getElementById('teamReportStartTimeInput').value = item.start_time ? item.start_time.substring(0, 5) : '';
        document.getElementById('teamReportEndTimeInput').value = item.end_time ? item.end_time.substring(0, 5) : '';
        document.getElementById('teamReportSupervisorInput').value = item.supervisor_name || '';

        teamExistingPhotos = [];
        if (item.photo_path) {
            try {
                const parsed = JSON.parse(item.photo_path);
                if (Array.isArray(parsed)) teamExistingPhotos = parsed;
                else if (typeof item.photo_path === 'string') teamExistingPhotos = [item.photo_path];
            } catch(e) {
                teamExistingPhotos = [item.photo_path];
            }
        }
        renderTeamPhotos();

        const modal = document.getElementById('teamReportModal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    };

    function renderTeamPhotos() {
        const grid = document.getElementById('teamExistingPhotosGrid');
        const section = document.getElementById('teamExistingPhotosSection');
        const hiddenCont = document.getElementById('teamKeepPhotosContainer');

        grid.innerHTML = '';
        hiddenCont.innerHTML = '';

        if (teamExistingPhotos.length === 0) {
            section.classList.add('hidden');
            return;
        }

        section.classList.remove('hidden');
        teamExistingPhotos.forEach((path, idx) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'keep_photos[]';
            input.value = path;
            hiddenCont.appendChild(input);

            const card = document.createElement('div');
            card.className = 'relative rounded-xl overflow-hidden border border-slate-200 bg-slate-50 h-24 group flex items-center justify-center';
            card.innerHTML = `
                <img src="serve_file.php?file=${encodeURIComponent(path.replace(/\\\\/g, '/'))}" class="w-full h-full object-cover">
                <button type="button" onclick="removeTeamPhoto(${idx})" class="absolute top-1 right-1 w-6 h-6 rounded-lg bg-rose-600 text-white flex items-center justify-center text-xs font-black shadow-sm hover:bg-rose-700 transition-colors" title="Hapus foto ini">
                    ✕
                </button>
            `;
            grid.appendChild(card);
        });
    }

    window.removeTeamPhoto = function(index) {
        teamExistingPhotos.splice(index, 1);
        renderTeamPhotos();
    };

    window.closeTeamReportModal = function() {
        const modal = document.getElementById('teamReportModal');
        modal.classList.add('hidden');
        modal.style.display = 'none';
    };
})();
</script>
