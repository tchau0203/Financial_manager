<?php
// GET /api/shared/qr.php?settlement_id=5
//     hoặc ?to_user_id=2&amount=150000[&content=...]
//     hoặc ?bank_code=VCB&account_number=0123456789&account_name=...&amount=...
// -> trả URL ảnh mã VietQR (img.vietqr.io, chuẩn Napas 247) để quét chuyển khoản.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();
$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Phương thức không hợp lệ'], 405);
}

$bankCode = '';
$account = '';
$accName = '';
$amount = 0;
$content = trim((string)($_GET['content'] ?? ''));
$settlementId = filter_var($_GET['settlement_id'] ?? null, FILTER_VALIDATE_INT);
$toUserId = filter_var($_GET['to_user_id'] ?? null, FILTER_VALIDATE_INT);

if ($settlementId) {
    // Lấy số tiền + người nhận từ khoản nợ; chỉ thành viên của quỹ mới xem được
    $st = $pdo->prepare('SELECT s.space_id, s.to_user_id, s.amount, sp.name AS space_name
                         FROM shared_settlements s JOIN shared_spaces sp ON sp.id = s.space_id WHERE s.id = ?');
    $st->execute([$settlementId]);
    $s = $st->fetch();
    if (!$s) jsonResponse(['success' => false, 'message' => 'Không tìm thấy khoản thanh toán'], 404);
    $chk = $pdo->prepare('SELECT 1 FROM shared_members WHERE space_id = ? AND user_id = ?');
    $chk->execute([$s['space_id'], $userId]);
    if (!$chk->fetch()) jsonResponse(['success' => false, 'message' => 'Bạn không thuộc quỹ này'], 403);

    $toUserId = (int)$s['to_user_id'];
    $amount = (int)round((float)$s['amount']);
    if ($content === '') $content = 'Quyet toan quy ' . $s['space_name'];
} elseif ($toUserId) {
    $amount = (int)round((float)($_GET['amount'] ?? 0));
    // Chỉ cho tạo QR tới người cùng quỹ với mình (tránh dò thông tin ngân hàng người lạ)
    $chk = $pdo->prepare('SELECT 1 FROM shared_members a JOIN shared_members b ON a.space_id = b.space_id
                          WHERE a.user_id = ? AND b.user_id = ? LIMIT 1');
    $chk->execute([$userId, $toUserId]);
    if ($toUserId !== $userId && !$chk->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Người nhận không cùng quỹ với bạn'], 403);
    }
} else {
    $bankCode = trim((string)($_GET['bank_code'] ?? ''));
    $account = trim((string)($_GET['account_number'] ?? ''));
    $accName = trim((string)($_GET['account_name'] ?? ''));
    $amount = (int)round((float)($_GET['amount'] ?? 0));
}

if ($toUserId) {
    $st = $pdo->prepare('SELECT full_name, bank_code, bank_account, bank_account_name FROM users WHERE id = ?');
    $st->execute([$toUserId]);
    $u = $st->fetch();
    if (!$u || empty($u['bank_code']) || empty($u['bank_account'])) {
        jsonResponse(['success' => false, 'message' => 'Người nhận chưa cập nhật thông tin tài khoản ngân hàng'], 422);
    }
    $bankCode = $u['bank_code'];
    $account = $u['bank_account'];
    $accName = $u['bank_account_name'] ?: $u['full_name'];
}

if (!preg_match('/^[A-Za-z0-9]{2,20}$/', $bankCode) || !preg_match('/^[0-9]{6,20}$/', $account)) {
    jsonResponse(['success' => false, 'message' => 'Mã ngân hàng hoặc số tài khoản không hợp lệ'], 400);
}
if ($amount < 0 || $amount > 100000000000) {
    jsonResponse(['success' => false, 'message' => 'Số tiền không hợp lệ'], 400);
}
if ($content === '') $content = 'Chuyen khoan CashManager';
$content = mb_substr($content, 0, 50);

$qs = ['addInfo' => $content];
if ($amount > 0) $qs['amount'] = $amount;
if ($accName !== '') $qs['accountName'] = $accName;
$qrUrl = 'https://img.vietqr.io/image/' . rawurlencode($bankCode) . '-' . rawurlencode($account)
       . '-compact2.png?' . http_build_query($qs, '', '&', PHP_QUERY_RFC3986);

jsonResponse([
    'success' => true,
    'data' => [
        'qr_url'         => $qrUrl,
        'bank_code'      => $bankCode,
        'account_number' => $account,
        'account_name'   => $accName,
        'amount'         => $amount,
        'content'        => $content,
    ],
]);
