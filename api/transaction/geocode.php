<?php
// GET /api/transaction/geocode.php?q=<address>
// Proxy geocoding endpoint: gọi Photon và Nominatim từ phía server PHP
// Đảm bảo không bị CORS, không bị chặn bởi adblocker trong trình duyệt, có User-Agent hợp lệ theo chính sách OSM.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Phương thức không hợp lệ'], 405);
}

// Bắt buộc đăng nhập
$userId = requireAuth();

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    jsonResponse(['success' => false, 'message' => 'Thiếu địa chỉ tìm kiếm'], 400);
}

if (mb_strlen($query, 'UTF-8') > 255) {
    $query = mb_substr($query, 0, 255, 'UTF-8');
}

// Danh sách các biến thể truy vấn:
// 1. Nguyên văn (ví dụ: "20 tăng nhơn phú")
// 2. Bỏ số nhà (ví dụ: "tăng nhơn phú")
// 3. Thêm ngữ cảnh ", Việt Nam"
$queries = [$query];
$noNumber = preg_replace('/^\s*(số\s*)?[\d\/\-]+[a-zA-Z]?\s*[,\s]\s*/iu', '', $query);
$noNumber = trim($noNumber ?? '');
if ($noNumber !== '' && strcasecmp($noNumber, $query) !== 0) {
    $queries[] = $noNumber;
}
if (!preg_match('/việt nam|vietnam/i', $query)) {
    $queries[] = ($noNumber !== '' ? $noNumber : $query) . ', Việt Nam';
}

function fetchUrl($url, $timeout = 4) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'CashManagerApp/1.0 (https://github.com/cashmanager; contact: admin@cashmanager.local)',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: vi,en;q=0.8'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200 && $response) {
            return $response;
        }
    }

    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: CashManagerApp/1.0 (admin@cashmanager.local)\r\nAccept: application/json\r\nAccept-Language: vi,en;q=0.8\r\n",
            'timeout' => $timeout,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ];
    $ctx = stream_context_create($opts);
    return @file_get_contents($url, false, $ctx);
}

// 1. Thử gọi Photon API (rất nhanh, chuẩn OpenStreetMap)
foreach ($queries as $idx => $q) {
    $photonUrl = 'https://photon.komoot.io/api/?q=' . urlencode($q) . '&limit=1&lang=default';
    $raw = fetchUrl($photonUrl, 3);
    if ($raw) {
        $data = json_decode($raw, true);
        if (!empty($data['features'][0]['geometry']['coordinates'])) {
            $coords = $data['features'][0]['geometry']['coordinates'];
            $lng = (float)$coords[0];
            $lat = (float)$coords[1];
            $props = $data['features'][0]['properties'] ?? [];
            $name = $props['name'] ?? $props['street'] ?? $q;
            $displayParts = array_filter([
                $props['name'] ?? '',
                $props['street'] ?? '',
                $props['district'] ?? '',
                $props['city'] ?? $props['state'] ?? '',
                $props['country'] ?? ''
            ]);
            $displayName = !empty($displayParts) ? implode(', ', array_unique($displayParts)) : $name;

            jsonResponse([
                'success' => true,
                'data' => [
                    'lat' => $lat,
                    'lng' => $lng,
                    'name' => $name,
                    'display_name' => $displayName,
                    'approx' => ($idx > 0)
                ]
            ]);
        }
    }
}

// 2. Thử gọi Nominatim OSM (chính chủ OSM)
foreach ($queries as $idx => $q) {
    $nomUrl = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&accept-language=vi&countrycodes=vn&q=' . urlencode($q);
    $raw = fetchUrl($nomUrl, 4);
    if ($raw) {
        $list = json_decode($raw, true);
        if (is_array($list) && !empty($list[0])) {
            $item = $list[0];
            jsonResponse([
                'success' => true,
                'data' => [
                    'lat' => (float)$item['lat'],
                    'lng' => (float)$item['lon'],
                    'name' => $item['name'] ?? explode(',', $item['display_name'])[0],
                    'display_name' => $item['display_name'] ?? $q,
                    'approx' => ($idx > 0)
                ]
            ]);
        }
    }
}

jsonResponse([
    'success' => false,
    'message' => 'Không tìm thấy tọa độ cho địa chỉ này. Hãy thử nhập chi tiết hơn (ví dụ: Tăng Nhơn Phú B, Thủ Đức, TP.HCM) hoặc bấm trực tiếp trên bản đồ.'
], 404);
