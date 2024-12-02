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

// تحديد مسار الملف
$filePath = __DIR__ . '/content/data.json';
$dirPath = dirname($filePath);

// التأكد من وجود المجلد
if (!is_dir($dirPath)) {
    if (!mkdir($dirPath, 0777, true)) {
        http_response_code(500);
        error_log('Failed to create directory: ' . $dirPath . ' at ' . date('Y-m-d H:i:s'));
        echo json_encode(['error' => 'فشل في إنشاء المجلد']);
        exit;
    }
}

// إذا كان الملف غير موجود، قم بإنشاء هيكل البيانات الأساسي
if (!file_exists($filePath)) {
    $data = [
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
}

// محاولة الكتابة في الملف
try {
    if (file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false) {
        echo json_encode(['success' => true, 'message' => 'تم الحفظ بنجاح']);
    } else {
        throw new Exception('فشل في كتابة الملف');
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('Error saving file: ' . $e->getMessage() . ' at ' . date('Y-m-d H:i:s'));
    echo json_encode(['error' => 'فشل في حفظ البيانات. يرجى المحاولة مرة أخرى لاحقًا.']);
}
