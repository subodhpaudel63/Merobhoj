<?php
/**
 * api_expenses.php — CRUD + reporting for the admin Expenses page.
 *
 *   GET  ?action=list [&from&to&category&method&q]
 *          → rows + stats (total / count / daily_avg / top_category) + per-category breakdown,
 *            all scoped to the same filter so the page can never show two totals for one range.
 *   GET  ?action=options            → category + payment-method allow-lists
 *   POST {action:'create', ...}
 *   POST {action:'update', id, ...}
 *   POST {action:'delete', id}
 *
 * Expenses carry no approval state by design — a row here is a recorded fact, which is why
 * admin/finance_data.php can sum the whole date range without filtering.
 *
 * Lives under admin/api/ so require_role()'s "/api/" detection answers unauthenticated
 * callers with 401 JSON rather than an HTML redirect.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/role_check.php';

// Management only. 'admin' is the universal override inside require_role().
$panelUser = require_role($conn, ['admin', 'manager']);

/** Spending buckets. Free text is rejected so the breakdown never fragments. */
const EXPENSE_CATEGORIES = [
    'Ingredients',
    'Salaries',
    'Rent',
    'Utilities',
    'Equipment',
    'Maintenance',
    'Marketing',
    'Delivery & Fuel',
    'Packaging',
    'Licenses & Taxes',
    'Miscellaneous',
];

/** How the money left the business. */
const EXPENSE_PAYMENT_METHODS = ['Cash', 'eSewa', 'Khalti', 'Bank Transfer', 'Cheque', 'Credit'];

/** DECIMAL(10,2) tops out at 99,999,999.99 — reject before MySQL truncates. */
const EXPENSE_MAX_AMOUNT = 99999999.99;

/** Send JSON and stop. */
function exp_json(array $payload): void
{
    echo json_encode($payload);
    exit;
}

/** Merge JSON body + form POST, so either content type works. */
function exp_input(): array
{
    $data = $_POST;
    $raw  = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }
    return $data;
}

/** 403 unless a valid panel CSRF token is present. */
function exp_require_csrf(array $input): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf'] ?? null);
    if (!verify_panel_csrf(is_string($token) ? $token : null)) {
        http_response_code(403);
        exp_json(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
    }
}

/** True only for a real calendar date in Y-m-d form (rejects 2026-02-31). */
function exp_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

/**
 * Validate and normalize a submitted expense.
 *
 * @return array{0: array<string,mixed>|null, 1: string} [clean fields, error message]
 */
function exp_validate(array $in): array
{
    $date   = trim((string)($in['date'] ?? ''));
    $title  = trim((string)($in['title'] ?? ''));
    $cat    = trim((string)($in['category'] ?? ''));
    $method = trim((string)($in['payment_method'] ?? ''));
    $rawAmt = $in['amount'] ?? '';

    if (!exp_valid_date($date)) {
        return [null, 'Pick a valid date.'];
    }
    if ($title === '') {
        return [null, 'Give the expense a short description.'];
    }
    if (mb_strlen($title) > 150) {
        return [null, 'Keep the description under 150 characters.'];
    }
    if (!is_numeric($rawAmt)) {
        return [null, 'Enter the amount as a number.'];
    }
    $amount = round((float)$rawAmt, 2);
    if ($amount <= 0) {
        return [null, 'Amount must be greater than zero.'];
    }
    if ($amount > EXPENSE_MAX_AMOUNT) {
        return [null, 'That amount is too large to record.'];
    }
    if (!in_array($cat, EXPENSE_CATEGORIES, true)) {
        return [null, 'Choose a category from the list.'];
    }
    if (!in_array($method, EXPENSE_PAYMENT_METHODS, true)) {
        return [null, 'Choose a payment method from the list.'];
    }

    return [[
        'date'           => $date,
        'category'       => $cat,
        'title'          => $title,
        'description'    => mb_substr(trim((string)($in['description'] ?? '')), 0, 2000),
        'amount'         => $amount,
        'vendor'         => mb_substr(trim((string)($in['vendor'] ?? '')), 0, 120),
        'payment_method' => $method,
        'reference_no'   => mb_substr(trim((string)($in['reference_no'] ?? '')), 0, 60),
    ], ''];
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

/* ================================ MUTATIONS ================================ */
if ($isPost) {
    $input = exp_input();
    exp_require_csrf($input);

    $action = trim((string)($input['action'] ?? ''));

    /* ---- delete ---- */
    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            exp_json(['success' => false, 'message' => 'Missing expense id.']);
        }
        $stmt = $conn->prepare("DELETE FROM `expenses` WHERE `id` = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $gone = $stmt->affected_rows;
        $stmt->close();

        exp_json($gone > 0
            ? ['success' => true,  'message' => 'Expense deleted.']
            : ['success' => false, 'message' => 'That expense no longer exists.']);
    }

    /* ---- create / update ---- */
    if ($action === 'create' || $action === 'update') {
        [$f, $err] = exp_validate($input);
        if ($f === null) {
            exp_json(['success' => false, 'message' => $err]);
        }

        if ($action === 'create') {
            $stmt = $conn->prepare(
                "INSERT INTO `expenses`
                    (`date`, `category`, `title`, `description`, `amount`,
                     `vendor`, `payment_method`, `reference_no`, `recorded_by`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'ssssdsssi',
                $f['date'], $f['category'], $f['title'], $f['description'], $f['amount'],
                $f['vendor'], $f['payment_method'], $f['reference_no'], $panelUser['id']
            );
            $stmt->execute();
            $newId = (int)$conn->insert_id;
            $stmt->close();

            exp_json(['success' => true, 'message' => 'Expense recorded.', 'id' => $newId]);
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            exp_json(['success' => false, 'message' => 'Missing expense id.']);
        }
        // recorded_by is re-stamped to whoever last touched the row, so the
        // table's "Recorded by" column always names someone accountable for
        // the figures currently shown.
        $stmt = $conn->prepare(
            "UPDATE `expenses`
                SET `date` = ?, `category` = ?, `title` = ?, `description` = ?, `amount` = ?,
                    `vendor` = ?, `payment_method` = ?, `reference_no` = ?, `recorded_by` = ?
              WHERE `id` = ?"
        );
        $stmt->bind_param(
            'ssssdsssii',
            $f['date'], $f['category'], $f['title'], $f['description'], $f['amount'],
            $f['vendor'], $f['payment_method'], $f['reference_no'], $panelUser['id'], $id
        );
        $stmt->execute();
        $stmt->close();

        // affected_rows is 0 for a no-op save as well as a missing id, so confirm
        // existence separately rather than reporting a phantom failure.
        $check = $conn->prepare("SELECT 1 FROM `expenses` WHERE `id` = ? LIMIT 1");
        $check->bind_param('i', $id);
        $check->execute();
        $exists = (bool)$check->get_result()->fetch_row();
        $check->close();

        exp_json($exists
            ? ['success' => true,  'message' => 'Expense updated.']
            : ['success' => false, 'message' => 'That expense no longer exists.']);
    }

    exp_json(['success' => false, 'message' => 'Unknown action.']);
}

/* ================================== READS ================================== */
$action = $_GET['action'] ?? '';

if ($action === 'options') {
    exp_json([
        'success'         => true,
        'categories'      => EXPENSE_CATEGORIES,
        'payment_methods' => EXPENSE_PAYMENT_METHODS,
    ]);
}

if ($action !== 'list') {
    exp_json(['success' => false, 'message' => 'Unknown action.']);
}

/* ---- filters (default: the current month) ---- */
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to']   ?? ''));
if (!exp_valid_date($from)) { $from = date('Y-m-01'); }
if (!exp_valid_date($to))   { $to   = date('Y-m-t');  }
if ($from > $to) {
    [$from, $to] = [$to, $from];   // a backwards range is a slip, not an error
}

$category = trim((string)($_GET['category'] ?? ''));
$method   = trim((string)($_GET['method']   ?? ''));
$search   = trim((string)($_GET['q']        ?? ''));

// One WHERE clause shared by the rows query and the breakdown query, so the
// stat cards, the bars and the table can never disagree about the same range.
$where = ['e.`date` BETWEEN ? AND ?'];
$types = 'ss';
$args  = [$from, $to];

if (in_array($category, EXPENSE_CATEGORIES, true)) {
    $where[] = 'e.`category` = ?';
    $types  .= 's';
    $args[]  = $category;
}
if (in_array($method, EXPENSE_PAYMENT_METHODS, true)) {
    $where[] = 'e.`payment_method` = ?';
    $types  .= 's';
    $args[]  = $method;
}
if ($search !== '') {
    $where[] = '(e.`title` LIKE ? OR e.`vendor` LIKE ? OR e.`reference_no` LIKE ?)';
    $types  .= 'sss';
    $like    = '%' . $search . '%';
    array_push($args, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

/* ---- rows ---- */
$sql = "SELECT e.`id`, e.`date`, e.`category`, e.`title`, e.`description`, e.`amount`,
               e.`vendor`, e.`payment_method`, e.`reference_no`, e.`created_at`,
               u.`name` AS recorded_by_name
          FROM `expenses` e
     LEFT JOIN `users` u ON u.`id` = e.`recorded_by`
         WHERE {$whereSql}
      ORDER BY e.`date` DESC, e.`id` DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$args);
$stmt->execute();
$res = $stmt->get_result();

$rows  = [];
$total = 0.0;
while ($r = $res->fetch_assoc()) {
    $r['id']     = (int)$r['id'];
    $r['amount'] = (float)$r['amount'];
    $total      += $r['amount'];
    $rows[]      = $r;
}
$stmt->close();

/* ---- per-category breakdown ---- */
$bSql = "SELECT e.`category`, SUM(e.`amount`) AS amount, COUNT(*) AS entries
           FROM `expenses` e
          WHERE {$whereSql}
       GROUP BY e.`category`
       ORDER BY amount DESC";

$bStmt = $conn->prepare($bSql);
$bStmt->bind_param($types, ...$args);
$bStmt->execute();
$bRes = $bStmt->get_result();

$breakdown = [];
while ($b = $bRes->fetch_assoc()) {
    $amount      = (float)$b['amount'];
    $breakdown[] = [
        'category' => $b['category'],
        'amount'   => $amount,
        'entries'  => (int)$b['entries'],
        'share'    => $total > 0 ? round($amount / $total * 100, 1) : 0.0,
    ];
}
$bStmt->close();

/* ---- stats, all scoped to the same filter ---- */
// Averaged over the days in the selected range (not just the days with entries),
// so "per day" answers "what does this range cost me daily".
$days = (int)((new DateTime($from))->diff(new DateTime($to))->days) + 1;

exp_json([
    'success' => true,
    'filters' => [
        'from'     => $from,
        'to'       => $to,
        'category' => $category,
        'method'   => $method,
        'q'        => $search,
    ],
    'stats' => [
        'total'        => round($total, 2),
        'count'        => count($rows),
        'days'         => $days,
        'daily_avg'    => $days > 0 ? round($total / $days, 2) : 0.0,
        'top_category' => $breakdown[0] ?? null,
    ],
    'breakdown'       => $breakdown,
    'rows'            => $rows,
    'categories'      => EXPENSE_CATEGORIES,
    'payment_methods' => EXPENSE_PAYMENT_METHODS,
]);
