<?php
// admin/reports.php
require_once 'layout_head.php';

// Auth & Permissions
$auth->requireRole(['admin', 'branch_manager']);
$currentUser = $auth->getCurrentUser();
$isManager = ($currentUser['role'] === 'branch_manager');
$userBranchId = $currentUser['branch_id'];

$db = Database::getInstance()->getConnection();

// --- FILTROS (LOGICA DE TURNO INTELIGENTE) ---
$current_hour = (int)date('H');
$default_date = date('Y-m-d');
if ($current_hour < 9) {
    $default_date = date('Y-m-d', strtotime('-1 day'));
}

$filter_date = $_GET['date'] ?? $default_date;
$start = $filter_date . ' 09:00:00';
$end = date('Y-m-d H:i:s', strtotime($start . ' +1 day'));

// --- QUERY BUILDER ---
// Base params (Time window)
$baseParams = [$start, $end];
$branchSQL = "";

// Apply Branch Filter if Manager
if ($isManager) {
    $branchSQL = " AND s.branch_id = ? ";
    $baseParams[] = $userBranchId;
} 
// Optional: Allow Admin to filter by specific branch (GET param)
elseif (isset($_GET['branch_id']) && !empty($_GET['branch_id'])) {
    $branchSQL = " AND s.branch_id = ? ";
    $baseParams[] = $_GET['branch_id'];
}

// --- DATA FETCHING ---
function safeQuery($db, $sql, $params) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

// 1. Stats Globales
$sql_stats = "SELECT 
    COUNT(*) as txns, SUM(amount) as total,
    SUM(CASE WHEN payment_method='cash' THEN amount ELSE 0 END) as cash,
    SUM(CASE WHEN payment_method='qr' THEN amount ELSE 0 END) as qr
    FROM sales s WHERE s.created_at >= ? AND s.created_at < ? $branchSQL";
$stats = safeQuery($db, $sql_stats, $baseParams)[0] ?? ['txns'=>0, 'total'=>0, 'cash'=>0, 'qr'=>0];

// 2. Data Sets
$data_machines = safeQuery($db, 
    "SELECT m.name as Concepto, COUNT(*) as Cantidad, SUM(s.amount) as Total 
     FROM sales s JOIN machines m ON s.machine_id = m.id 
     WHERE s.created_at >= ? AND s.created_at < ? $branchSQL GROUP BY m.id ORDER BY Total DESC", 
    $baseParams);

$data_employees = safeQuery($db, 
    "SELECT u.username as Concepto, COUNT(*) as Cantidad, SUM(s.amount) as Total 
     FROM sales s JOIN users u ON s.user_id = u.id 
     WHERE s.created_at >= ? AND s.created_at < ? $branchSQL GROUP BY u.id ORDER BY Total DESC", 
    $baseParams);

$data_branches = safeQuery($db, 
    "SELECT b.name as Concepto, COUNT(*) as Cantidad, SUM(s.amount) as Total 
     FROM sales s LEFT JOIN branches b ON s.branch_id = b.id 
     WHERE s.created_at >= ? AND s.created_at < ? $branchSQL GROUP BY b.id ORDER BY Total DESC", 
    $baseParams);

$data_detail = safeQuery($db, 
    "SELECT DATE_FORMAT(s.created_at, '%H:%i') as Hora, COALESCE(b.name, 'N/A') as Local, u.username as Vendedor, m.name as Juego, s.payment_method as Metodo, s.amount as Monto
     FROM sales s 
     JOIN machines m ON s.machine_id = m.id 
     JOIN users u ON s.user_id = u.id 
     LEFT JOIN branches b ON s.branch_id = b.id
     WHERE s.created_at >= ? AND s.created_at < ? $branchSQL ORDER BY s.created_at DESC", 
    $baseParams);

// Estructura Global Manual
$data_global = [
    ['Concepto' => 'Total Facturado', 'Cantidad' => (string)$stats['txns'], 'Total' => $stats['total']],
    ['Concepto' => 'Total Efectivo', 'Cantidad' => '-', 'Total' => $stats['cash']],
    ['Concepto' => 'Mercado Pago QR', 'Cantidad' => '-', 'Total' => $stats['qr']]
];

// JSON Seguro
$json_data = json_encode([
    'date_str' => date('d/m/Y', strtotime($filter_date)),
    'global' => ['title' => 'RESUMEN ' . ($isManager ? 'DE SUCURSAL' : 'GLOBAL'), 'cols' => ['Concepto', 'Cantidad', 'Total'], 'rows' => $data_global],
    'employees' => ['title' => 'VENTAS POR EMPLEADO', 'cols' => ['Concepto', 'Cantidad', 'Total'], 'rows' => $data_employees],
    'machines' => ['title' => 'VENTAS POR MÁQUINA', 'cols' => ['Concepto', 'Cantidad', 'Total'], 'rows' => $data_machines],
    'branches' => ['title' => 'VENTAS POR SUCURSAL', 'cols' => ['Concepto', 'Cantidad', 'Total'], 'rows' => $data_branches],
    'detail' => ['title' => 'DETALLE DE OPERACIONES', 'cols' => ['Hora', 'Local', 'Vendedor', 'Juego', 'Metodo', 'Monto'], 'rows' => $data_detail]
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

// Get Branches List for Admin Filter
$branchesList = [];
if(!$isManager) {
    $branchesList = $db->query("SELECT id, name FROM branches WHERE status=1")->fetchAll();
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-3 pt-3 border-bottom pb-2">
    <h1 class="h3 mb-2 mb-md-0"><i class="bi bi-bar-chart-fill me-2"></i>Reportes <?= $isManager ? '(Mi Sucursal)' : '' ?></h1>
    <form class="d-flex flex-wrap gap-2 w-100 w-md-auto justify-content-end">
        <?php if(!$isManager): ?>
        <select name="branch_id" class="form-select w-auto">
            <option value="">-- Todas las Sucursales --</option>
            <?php foreach($branchesList as $b): ?>
                <option value="<?= $b['id'] ?>" <?= (isset($_GET['branch_id']) && $_GET['branch_id'] == $b['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <input type="date" name="date" class="form-control w-auto" value="<?= $filter_date ?>">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-1">
            <i class="bi bi-funnel"></i> <span class="d-none d-sm-inline">Filtrar</span>
        </button>
    </form>
</div>

<!-- Selectores Responsive -->
<div class="row g-2 mb-3">
    <?php 
    $tabs = [
        'global' => ['icon' => 'globe', 'label' => 'Resumen'],
        'employees' => ['icon' => 'people', 'label' => 'Empleados'],
        'machines' => ['icon' => 'controller', 'label' => 'Juegos'],
        'branches' => ['icon' => 'shop', 'label' => 'Locales'],
        'detail' => ['icon' => 'list-ul', 'label' => 'Detalle']
    ];
    foreach($tabs as $k => $t): 
        // Hide "Branches" tab if Manager (redundant, but can keep if desired)
        // Keeping it allows them to see their own branch summary in that format.
    ?>
    <div class="col-4 col-md">
        <div class="card selector-card text-center p-2 h-100" onclick="loadTab('<?= $k ?>', this)" id="tab-<?= $k ?>">
            <i class="bi bi-<?= $t['icon'] ?> d-block fs-5 mb-1"></i>
            <small class="fw-bold d-block text-truncate"><?= $t['label'] ?></small>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-primary text-truncate pe-2" id="view-title">RESUMEN</h6>
        <button class="btn btn-sm btn-outline-danger flex-shrink-0" onclick="exportPDF()">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i>PDF
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle" id="main-table">
                <thead class="table-light text-secondary small text-uppercase">
                    <tr id="table-head"></tr>
                </thead>
                <tbody id="table-body"></tbody>
            </table>
        </div>
        <div id="no-data" class="p-5 text-center text-muted d-none">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            Sin datos para este período
        </div>
    </div>
</div>

<!-- PDF Libs -->
<script src="../assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="../assets/vendor/pdfmake/vfs_fonts.js"></script>

<style>
    .selector-card { cursor: pointer; border: 1px solid #eee; transition: all 0.2s; user-select: none; }
    .selector-card:active { transform: scale(0.95); }
    .selector-card.active { background-color: #0d6efd; color: white; border-color: #0d6efd; box-shadow: 0 4px 6px rgba(13,110,253,0.3); }
    @media (max-width: 576px) {
        .selector-card small { font-size: 0.7rem; }
        .h3 { font-size: 1.5rem; }
    }
</style>

<script>
// Load Data safely
const reportDB = <?= $json_data ?>;
let currentTab = 'global';

function fmtMoney(val) {
    if(val === null || val === undefined || val === '-') return '-';
    let num = parseFloat(val);
    if(isNaN(num)) return val;
    return '$ ' + num.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function loadTab(key, el) {
    currentTab = key;
    const data = reportDB[key];
    
    // UI Updates
    document.querySelectorAll('.selector-card').forEach(c => c.classList.remove('active'));
    if(el) el.classList.add('active');
    else {
        const target = document.getElementById('tab-'+key);
        if(target) target.classList.add('active');
    }
    
    document.getElementById('view-title').innerText = data.title;
    
    const thead = document.getElementById('table-head');
    thead.innerHTML = '';
    data.cols.forEach(col => {
        let th = document.createElement('th');
        th.innerText = col;
        if(col === 'Total' || col === 'Monto') th.className = 'text-end';
        if(col === 'Cantidad') th.className = 'text-center';
        thead.appendChild(th);
    });
    
    const tbody = document.getElementById('table-body');
    tbody.innerHTML = '';
    const noData = document.getElementById('no-data');
    
    if(!data.rows || data.rows.length === 0) {
        document.getElementById('main-table').style.display = 'none';
        noData.classList.remove('d-none');
    } else {
        document.getElementById('main-table').style.display = 'table';
        noData.classList.add('d-none');
        
        data.rows.forEach(row => {
            let tr = document.createElement('tr');
            data.cols.forEach(col => {
                let td = document.createElement('td');
                let val = row[col];
                
                if(col === 'Total' || col === 'Monto') {
                    td.innerText = fmtMoney(val);
                    td.className = 'text-end fw-bold text-dark';
                } else if(col === 'Cantidad') {
                    td.innerText = val;
                    td.className = 'text-center';
                } else if(col === 'Metodo') {
                    td.innerHTML = (val === 'qr') 
                        ? '<span class="badge bg-info text-dark">MP</span>' 
                        : '<span class="badge bg-success">EFT</span>';
                } else {
                    td.innerText = val || '-';
                    td.className = 'text-nowrap';
                }
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    }
}

function exportPDF() {
    const data = reportDB[currentTab];
    const dateStr = reportDB.date_str;
    
    const body = [];
    
    const headers = data.cols.map(c => ({ 
        text: c.toUpperCase(), 
        style: 'tableHeader', 
        alignment: (c === 'Total' || c === 'Monto') ? 'right' : (c === 'Cantidad' ? 'center' : 'left')
    }));
    body.push(headers);
    
    let totalSum = 0;
    
    data.rows.forEach(r => {
        const row = data.cols.map(c => {
            let rawData = r[c];
            if(c === 'Total' || c === 'Monto') {
                totalSum += parseFloat(rawData || 0);
                return { text: fmtMoney(rawData), alignment: 'right' };
            }
            if(c === 'Cantidad') return { text: rawData, alignment: 'center' };
            if(c === 'Metodo') return { text: (rawData==='qr'?'MP QR':'EFECTIVO'), alignment: 'left' };
            
            return { text: (rawData || '-').toString(), alignment: 'left' };
        });
        body.push(row);
    });

    if(currentTab !== 'global' && data.rows.length > 0) {
        const footerLength = data.cols.length;
        const footerRow = new Array(footerLength).fill({ text: '', border: [false, false, false, false] });
        footerRow[footerLength - 2] = { text: 'TOTAL:', bold: true, alignment: 'right', fillColor: '#eeeeee' };
        footerRow[footerLength - 1] = { text: fmtMoney(totalSum), bold: true, alignment: 'right', fillColor: '#eeeeee' };
        body.push(footerRow);
    }
    
    const docDefinition = {
        pageSize: 'A4',
        pageMargins: [30, 30, 30, 30],
        content: [
            { text: 'SPACEPARK - SISTEMA POS', style: 'header', alignment: 'center', color: '#0d6efd' },
            { text: `REPORTE: ${data.title}`, style: 'subheader', alignment: 'center' },
            { text: `FECHA DE TURNO: ${dateStr}`, style: 'small', alignment: 'center', margin: [0,0,0,15] },
            {
                table: {
                    headerRows: 1,
                    widths: Array(data.cols.length).fill('*'),
                    body: body
                },
                layout: 'lightHorizontalLines'
            },
            { text: 'Documento generado automáticamente.', style: 'small', alignment: 'center', margin: [0, 20, 0, 0] }
        ],
        styles: {
            header: { fontSize: 18, bold: true, margin: [0, 0, 0, 5] },
            subheader: { fontSize: 12, bold: true, margin: [0, 0, 0, 5] },
            tableHeader: { bold: true, fontSize: 10, color: 'black', fillColor: '#f8f9fa' },
            small: { fontSize: 9, color: '#666' }
        },
        defaultStyle: { fontSize: 9 }
    };
    
    pdfMake.createPdf(docDefinition).download(`SpacePark_${currentTab}_${dateStr}.pdf`);
}

document.addEventListener('DOMContentLoaded', () => {
    loadTab('global');
});
</script>

<?php require_once 'layout_foot.php'; ?>
