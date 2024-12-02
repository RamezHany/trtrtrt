<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// إذا كانت الطريقة GET، نقوم بقراءة الملف
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $github_token = getenv('GITHUB_TOKEN');
    $repo_owner = getenv('GITHUB_OWNER');
    $repo_name = getenv('GITHUB_REPO');
    $file_path = 'content/data.json';

    $url = "https://api.github.com/repos/$repo_owner/$repo_name/contents/$file_path";
    $options = [
        'http' => [
            'header' => [
                'User-Agent: PHP Script',
                "Authorization: token $github_token",
                'Accept: application/vnd.github.v3+json'
            ],
            'method' => 'GET',
        ],
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($http_response_header[0] === 'HTTP/1.1 200 OK') {
        $file_info = json_decode($result, true);
        $content = base64_decode($file_info['content']);
        echo $content;
        exit;
    } else if ($http_response_header[0] === 'HTTP/1.1 404 Not Found') {
        // إذا لم يكن الملف موجوداً، نرجع الهيكل الافتراضي
        $default_content = [
            'pageDescription' => [
                'title' => '',
                'content' => ''
            ],
            'navigation' => [
                'logo' => '',
                'github_link' => '',
                'menu_items' => []
            ],
            'sections' => []
        ];
        echo json_encode($default_content);
        exit;
    } else {
        http_response_code(500);
        error_log('GitHub API Error (GET): ' . $result);
        echo json_encode(['error' => 'فشل في قراءة البيانات من GitHub']);
        exit;
    }
}

// التأكد من أن الطريقة POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    error_log('Invalid Method: ' . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['error' => 'طريقة غير مسموح بها']);
    exit;
}

// تلقي البيانات
$input = file_get_contents('php://input');
error_log('Received Input: ' . $input);

if (empty($input)) {
    http_response_code(400);
    error_log('Empty Input Received');
    echo json_encode(['error' => 'لم يتم إرسال أي بيانات']);
    exit;
}

$data = json_decode($input, true);

// التحقق من صحة البيانات
if ($data === null) {
    http_response_code(400);
    error_log('JSON decode error: ' . json_last_error_msg() . ' Input: ' . $input);
    echo json_encode(['error' => 'بيانات غير صالحة: ' . json_last_error_msg()]);
    exit;
}

// التحقق من هيكل البيانات
if (!isset($data['pageDescription'])) {
    http_response_code(400);
    error_log('Missing pageDescription in data: ' . json_encode($data));
    echo json_encode(['error' => 'هيكل البيانات غير صحيح - pageDescription مفقود']);
    exit;
}

if (!isset($data['sections'])) {
    http_response_code(400);
    error_log('Missing sections in data: ' . json_encode($data));
    echo json_encode(['error' => 'هيكل البيانات غير صحيح - sections مفقود']);
    exit;
}

// حفظ البيانات في ملف على GitHub
$github_token = getenv('GITHUB_TOKEN');
$repo_owner = getenv('GITHUB_OWNER');
$repo_name = getenv('GITHUB_REPO');
$file_path = 'content/data.json';

if (empty($github_token) || empty($repo_owner) || empty($repo_name)) {
    http_response_code(500);
    error_log('Missing environment variables: TOKEN=' . (empty($github_token) ? 'NO' : 'YES') . 
              ', OWNER=' . (empty($repo_owner) ? 'NO' : 'YES') . 
              ', REPO=' . (empty($repo_name) ? 'NO' : 'YES'));
    echo json_encode(['error' => 'متغيرات البيئة غير مكتملة']);
    exit;
}

// تجهيز البيانات للحفظ
$file_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// الحصول على SHA للملف الحالي (إذا كان موجوداً)
$url = "https://api.github.com/repos/$repo_owner/$repo_name/contents/$file_path";
$options = [
    'http' => [
        'header' => [
            'User-Agent: PHP Script',
            "Authorization: token $github_token",
            'Accept: application/vnd.github.v3+json'
        ],
        'method' => 'GET',
    ],
];
$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

$sha = '';
if ($http_response_header[0] === 'HTTP/1.1 200 OK') {
    $file_info = json_decode($result, true);
    $sha = $file_info['sha'];
}

// تحديث الملف على GitHub
$post_data = [
    'message' => 'تحديث المحتوى',
    'content' => base64_encode($file_content),
];

if ($sha) {
    $post_data['sha'] = $sha;
}

$options = [
    'http' => [
        'header' => [
            'User-Agent: PHP Script',
            "Authorization: token $github_token",
            'Accept: application/vnd.github.v3+json',
            'Content-Type: application/json'
        ],
        'method' => 'PUT',
        'content' => json_encode($post_data),
    ],
];
$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

if ($http_response_header[0] === 'HTTP/1.1 200 OK' || $http_response_header[0] === 'HTTP/1.1 201 Created') {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    error_log('GitHub API Error (PUT): ' . $result);
    echo json_encode(['error' => 'فشل في حفظ البيانات على GitHub']);
}
