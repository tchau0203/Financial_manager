<?php
/**
 * Gửi email (SMTP thuần qua socket, fallback hàm mail()).
 * Cấu hình trong .env:
 *   MAIL_HOST=smtp.gmail.com  MAIL_PORT=587  (587 = STARTTLS, 465 = SSL)
 *   MAIL_USER=you@gmail.com   MAIL_PASS=app_password
 *   MAIL_FROM=you@gmail.com   MAIL_FROM_NAME=CashManager
 * Nếu chưa cấu hình MAIL_HOST, hệ thống dùng mail() (có thể không gửi được trên XAMPP).
 */

function mailConfig() {
    $get = function ($k, $d = '') {
        $v = $_ENV[$k] ?? getenv($k);
        return ($v === false || $v === null || $v === '') ? $d : $v;
    };
    return [
        'host'      => $get('MAIL_HOST'),
        'port'      => (int)$get('MAIL_PORT', 587),
        'user'      => $get('MAIL_USER'),
        'pass'      => $get('MAIL_PASS'),
        'from'      => $get('MAIL_FROM', $get('MAIL_USER', 'no-reply@cashmanager.local')),
        'from_name' => $get('MAIL_FROM_NAME', 'CashManager'),
    ];
}

function smtpRead($fp) {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $data;
}

function smtpCmd($fp, $cmd, $expect) {
    if ($cmd !== null) fwrite($fp, $cmd . "\r\n");
    $res = smtpRead($fp);
    if (strpos($res, (string)$expect) !== 0) {
        throw new Exception("SMTP lỗi (cần $expect): " . trim($res));
    }
    return $res;
}

/**
 * Gửi email HTML. Trả về true/false, không bao giờ ném exception.
 */
function sendMail($to, $subject, $htmlBody) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $c = mailConfig();
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromName   = '=?UTF-8?B?' . base64_encode($c['from_name']) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        "From: $fromName <{$c['from']}>",
        'Date: ' . date('r'),
    ];
    $body = chunk_split(base64_encode($htmlBody));

    if ($c['host'] === '') {
        return @mail($to, $encSubject, $body, implode("\r\n", $headers));
    }

    try {
        $secure = $c['port'] === 465;
        $fp = @stream_socket_client(($secure ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'], $en, $es, 10);
        if (!$fp) throw new Exception("Không kết nối được SMTP: $es");
        stream_set_timeout($fp, 10);
        smtpCmd($fp, null, 220);
        smtpCmd($fp, 'EHLO cashmanager.local', 250);
        if (!$secure) {
            smtpCmd($fp, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('Không bật được TLS');
            }
            smtpCmd($fp, 'EHLO cashmanager.local', 250);
        }
        if ($c['user'] !== '') {
            smtpCmd($fp, 'AUTH LOGIN', 334);
            smtpCmd($fp, base64_encode($c['user']), 334);
            smtpCmd($fp, base64_encode($c['pass']), 235);
        }
        smtpCmd($fp, "MAIL FROM:<{$c['from']}>", 250);
        smtpCmd($fp, "RCPT TO:<$to>", 250);
        smtpCmd($fp, 'DATA', 354);
        $msg = implode("\r\n", $headers) . "\r\nTo: <$to>\r\nSubject: $encSubject\r\n\r\n" . $body;
        fwrite($fp, $msg . "\r\n.\r\n");
        smtpCmd($fp, null, 250);
        smtpCmd($fp, 'QUIT', 221);
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        error_log('[mail] ' . $e->getMessage());
        return false;
    }
}

function formatVnd($n) {
    return number_format((float)$n, 0, ',', '.') . ' đ';
}

/**
 * Soạn nội dung email cảnh báo ngân sách.
 * $status: 'warning' | 'exceeded'
 */
function buildBudgetAlertEmail($fullName, $catName, $limit, $spent, $pct, $status) {
    $exceeded = $status === 'exceeded';
    $color    = $exceeded ? '#DC2626' : '#F59E0B';
    $headline = $exceeded ? 'Bạn đã vượt ngân sách!' : 'Ngân sách sắp cạn!';
    $remain   = max(0, $limit - $spent);
    $over     = max(0, $spent - $limit);
    $name = htmlspecialchars($fullName ?: 'bạn', ENT_QUOTES, 'UTF-8');
    $cat  = htmlspecialchars($catName, ENT_QUOTES, 'UTF-8');
    $barW = min(100, round($pct));
    $lastRow = $exceeded
        ? '<tr><td style="padding:8px 0;color:#6B7280">Số tiền vượt</td><td style="text-align:right;font-weight:bold;color:#DC2626">' . formatVnd($over) . '</td></tr>'
        : '<tr><td style="padding:8px 0;color:#6B7280">Số tiền còn lại</td><td style="text-align:right;font-weight:bold;color:#059669">' . formatVnd($remain) . '</td></tr>';

    return '<!DOCTYPE html><html><body style="margin:0;background:#F3F4F6;font-family:Arial,sans-serif">'
        . '<div style="max-width:520px;margin:24px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">'
        . '<div style="background:' . $color . ';color:#fff;padding:20px 24px"><h2 style="margin:0">' . $headline . '</h2>'
        . '<div style="opacity:.9;font-size:13px;margin-top:4px">CashManager – Quản lý chi tiêu</div></div>'
        . '<div style="padding:24px"><p>Xin chào <b>' . $name . '</b>,</p>'
        . '<p>Danh mục <b>' . $cat . '</b> đã dùng <b style="color:' . $color . '">' . round($pct, 1) . '%</b> hạn mức tháng này.</p>'
        . '<div style="background:#E5E7EB;border-radius:8px;height:12px;overflow:hidden"><div style="width:' . $barW . '%;height:12px;background:' . $color . '"></div></div>'
        . '<table style="width:100%;margin-top:16px;border-collapse:collapse">'
        . '<tr><td style="padding:8px 0;color:#6B7280">Danh mục</td><td style="text-align:right;font-weight:bold">' . $cat . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#6B7280">Hạn mức</td><td style="text-align:right;font-weight:bold">' . formatVnd($limit) . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#6B7280">Đã chi</td><td style="text-align:right;font-weight:bold">' . formatVnd($spent) . '</td></tr>'
        . $lastRow . '</table>'
        . '<p style="color:#6B7280;font-size:12px;margin-top:24px">Bạn có thể tắt email này trong Cài đặt tài khoản.</p>'
        . '</div></div></body></html>';
}
