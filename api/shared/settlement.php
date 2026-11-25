<?php
// GET  /api/shared/settlement.php?space_id=1
//      -> tính số dư ròng từng thành viên, đề xuất danh sách chuyển khoản tối giản (Debt Simplification)
//         + lịch sử các khoản đã thanh toán.
// POST /api/shared/settlement.php {settlement_id}  -> xác nhận "Đã thanh toán" một khoản nợ
//
// Quy tắc: chi tiêu (type = expense) của quỹ được chia đều cho tất cả thành viên hiện tại.
// Các khoản đã settled được trừ vào số dư trước khi tính, nên danh sách pending luôn phản ánh phần còn nợ.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();
$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['GET', 'POST'], true)) {
    jsonResponse(['success' => false, 'message' => 'Phương thức không hợp lệ'], 405);
}

function isSpaceMember(PDO $pdo, int $spaceId, int $userId): bool {
    $st = $pdo->prepare('SELECT 1 FROM shared_members WHERE space_id = ? AND user_id = ?');
    $st->execute([$spaceId, $userId]);
    return (bool)$st->fetch();
}

// ---------- POST: xác nhận đã thanh toán ----------
if ($method === 'POST') {
    $data = getJsonInput();
    $settlementId = filter_var($data['settlement_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$settlementId) {
        jsonResponse(['success' => false, 'message' => 'Thiếu settlement_id'], 400);
    }

    $st = $pdo->prepare('SELECT id, space_id, from_user_id, to_user_id, status FROM shared_settlements WHERE id = ?');
    $st->execute([$settlementId]);
    $row = $st->fetch();
    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy khoản thanh toán'], 404);
    }
    if (!isSpaceMember($pdo, (int)$row['space_id'], $userId)) {
        jsonResponse(['success' => false, 'message' => 'Bạn không thuộc quỹ này'], 403);
    }
    // Chỉ người trả hoặc người nhận tiền mới được xác nhận
    if ($userId !== (int)$row['from_user_id'] && $userId !== (int)$row['to_user_id']) {
        jsonResponse(['success' => false, 'message' => 'Chỉ người trả hoặc người nhận mới được xác nhận khoản này'], 403);
    }
    if ($row['status'] === 'settled') {
        jsonResponse(['success' => false, 'message' => 'Khoản này đã được thanh toán trước đó'], 409);
    }

    $pdo->prepare("UPDATE shared_settlements SET status = 'settled', settled_at = NOW() WHERE id = ? AND status = 'pending'")
        ->execute([$settlementId]);
    jsonResponse(['success' => true, 'message' => 'Đã xác nhận thanh toán nợ', 'data' => ['id' => $settlementId, 'status' => 'settled']]);
}

// ---------- GET: tính toán quyết toán ----------
$spaceId = filter_var($_GET['space_id'] ?? null, FILTER_VALIDATE_INT);
if (!$spaceId) {
    jsonResponse(['success' => false, 'message' => 'Thiếu space_id'], 400);
}
if (!isSpaceMember($pdo, $spaceId, $userId)) {
    jsonResponse(['success' => false, 'message' => 'Bạn không thuộc quỹ này'], 403);
}

try {
    $pdo->beginTransaction();
    // Khóa dòng quỹ để 2 người mở cùng lúc không sinh trùng danh sách pending
    $pdo->prepare('SELECT id FROM shared_spaces WHERE id = ? FOR UPDATE')->execute([$spaceId]);

    // Thành viên
    $st = $pdo->prepare('SELECT u.id, u.full_name, u.username, u.bank_code, u.bank_account, u.bank_account_name
                         FROM shared_members sm JOIN users u ON u.id = sm.user_id WHERE sm.space_id = ?');
    $st->execute([$spaceId]);
    $members = [];
    foreach ($st->fetchAll() as $m) {
        $members[(int)$m['id']] = $m;
    }
    $count = count($members);

    // Tổng đã chi của từng thành viên
    $st = $pdo->prepare("SELECT user_id, SUM(amount) AS total FROM shared_transactions WHERE space_id = ? AND type = 'expense' GROUP BY user_id");
    $st->execute([$spaceId]);
    $paid = [];
    foreach ($st->fetchAll() as $r) {
        $paid[(int)$r['user_id']] = (float)$r['total'];
    }
    $totalExpense = array_sum($paid);
    $share = $count > 0 ? $totalExpense / $count : 0;

    // Số dư ròng = đã chi - phần phải chịu (dương: được nhận lại, âm: phải trả thêm)
    $net = [];
    foreach ($members as $id => $_) {
        $net[$id] = ($paid[$id] ?? 0) - $share;
    }

    // Trừ các khoản đã thanh toán xong
    $st = $pdo->prepare("SELECT from_user_id, to_user_id, amount FROM shared_settlements WHERE space_id = ? AND status = 'settled'");
    $st->execute([$spaceId]);
    foreach ($st->fetchAll() as $s) {
        $f = (int)$s['from_user_id']; $t = (int)$s['to_user_id'];
        if (isset($net[$f])) $net[$f] += (float)$s['amount'];
        if (isset($net[$t])) $net[$t] -= (float)$s['amount'];
    }

    // Tối giản dòng tiền: ghép người nợ nhiều nhất với người được nhận nhiều nhất
    $debtors = []; $creditors = [];
    foreach ($net as $id => $v) {
        if ($v < -1) $debtors[] = ['id' => $id, 'amt' => -$v];
        elseif ($v > 1) $creditors[] = ['id' => $id, 'amt' => $v];
    }
    usort($debtors, fn($a, $b) => $b['amt'] <=> $a['amt']);
    usort($creditors, fn($a, $b) => $b['amt'] <=> $a['amt']);

    $transfers = [];
    $i = 0; $j = 0;
    while ($i < count($debtors) && $j < count($creditors)) {
        $pay = min($debtors[$i]['amt'], $creditors[$j]['amt']);
        $payInt = (int)round($pay);
        if ($payInt >= 1) {
            $transfers[] = ['from' => $debtors[$i]['id'], 'to' => $creditors[$j]['id'], 'amount' => $payInt];
        }
        $debtors[$i]['amt'] -= $pay;
        $creditors[$j]['amt'] -= $pay;
        if ($debtors[$i]['amt'] < 1) $i++;
        if ($creditors[$j]['amt'] < 1) $j++;
    }

    // Đồng bộ danh sách pending vào DB (xóa pending cũ, ghi pending mới)
    $pdo->prepare("DELETE FROM shared_settlements WHERE space_id = ? AND status = 'pending'")->execute([$spaceId]);
    $ins = $pdo->prepare("INSERT INTO shared_settlements (space_id, from_user_id, to_user_id, amount, status, note) VALUES (?, ?, ?, ?, 'pending', ?)");
    foreach ($transfers as $t) {
        $ins->execute([$spaceId, $t['from'], $t['to'], $t['amount'], 'Quyet toan quy chung']);
    }

    // Đọc lại (có id) kèm tên + thông tin ngân hàng người nhận
    $st = $pdo->prepare("
        SELECT s.id, s.from_user_id, s.to_user_id, s.amount, s.status, s.created_at, s.settled_at,
               uf.full_name AS from_user_name, ut.full_name AS to_user_name,
               ut.bank_code AS to_bank_code, ut.bank_account AS to_bank_account, ut.bank_account_name AS to_bank_account_name
        FROM shared_settlements s
        JOIN users uf ON uf.id = s.from_user_id
        JOIN users ut ON ut.id = s.to_user_id
        WHERE s.space_id = ? ORDER BY s.status ASC, s.amount DESC, s.id ASC");
    $st->execute([$spaceId]);
    $all = $st->fetchAll();
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'Không thể tính quyết toán: ' . $e->getMessage()], 500);
}

$pending = []; $settled = [];
foreach ($all as $r) {
    $r['id'] = (int)$r['id'];
    $r['from_user_id'] = (int)$r['from_user_id'];
    $r['to_user_id'] = (int)$r['to_user_id'];
    $r['amount'] = (float)$r['amount'];
    $r['has_bank_info'] = !empty($r['to_bank_code']) && !empty($r['to_bank_account']);
    if ($r['status'] === 'pending') $pending[] = $r; else $settled[] = $r;
}

$balances = [];
foreach ($members as $id => $m) {
    $balances[] = [
        'user_id'   => $id,
        'user_name' => $m['full_name'] ?: $m['username'],
        'paid'      => round($paid[$id] ?? 0),
        'balance'   => round($net[$id]),
    ];
}

jsonResponse([
    'success' => true,
    'data' => [
        'space_id'            => $spaceId,
        'member_count'        => $count,
        'total_group_expense' => round($totalExpense),
        'per_person_share'    => round($share),
        'balances'            => $balances,
        'pending'             => $pending,
        'settled'             => $settled,
        'is_all_settled'      => count($pending) === 0,
    ],
]);
