<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// تلقي البيانات
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// التحقق من صحة البيانات
if ($data === null) {
    http_response_code(400);
    error_log('JSON decode error: ' . json_last_error_msg() . ' at ' . date('Y-m-d H:i:s'));
    echo json_encode(['error' => 'بيانات غير صالحة: ' . json_last_error_msg()]);
    exit;
}

// حفظ البيانات في ملف على GitHub
$github_token = getenv('GITHUB_TOKEN');
$repo_owner = getenv('GITHUB_OWNER');
$repo_name = getenv('GITHUB_REPO');
$file_path = 'content/data.json';

// تجهيز البيانات للحفظ
$file_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// الحصول على SHA للملف الحالي (إذا كان موجوداً)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/$repo_owner/$repo_name/contents/$file_path");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: PHP Script',
    "Authorization: token $github_token",
    'Accept: application/vnd.github.v3+json'
]);

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$sha = '';
if ($http_code === 200) {
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

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/$repo_owner/$repo_name/contents/$file_path");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: PHP Script',
    "Authorization: token $github_token",
    'Accept: application/vnd.github.v3+json'
]);

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 || $http_code === 201) {
    echo json_encode(['success' => true, 'message' => 'تم الحفظ بنجاح']);
} else {
    http_response_code(500);
    error_log('GitHub API Error: ' . $result);
    echo json_encode(['error' => 'فشل في حفظ البيانات على GitHub']);
}
