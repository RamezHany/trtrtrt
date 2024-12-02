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
$file_path = 'content/page-content.json';

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
    'message' => 'تحديث محتوى الصفحة',
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
    // تحديث index.html
    try {
        // قراءة محتوى index.html من GitHub
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/$repo_owner/$repo_name/contents/index.html");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: PHP Script',
            "Authorization: token $github_token",
            'Accept: application/vnd.github.v3+json'
        ]);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $file_info = json_decode($result, true);
            $index_content = base64_decode($file_info['content']);
            $index_sha = $file_info['sha'];

            // تحديث المحتوى
            // تحديث الشعار
            $index_content = preg_replace(
                '/<img src="[^"]*"/',
                '<img src="' . $data['navigation']['logo'] . '"',
                $index_content
            );
            
            // تحديث رابط GitHub
            $index_content = preg_replace(
                '/href="https:\/\/github\.com[^"]*"/',
                'href="' . $data['navigation']['github_link'] . '"',
                $index_content
            );
            
            // تحديث القائمة الجانبية
            $menuItems = '';
            foreach ($data['navigation']['menu_items'] as $item) {
                $menuItems .= '<li class="js-btn" data-section="' . $item['id'] . '">' . $item['title'] . '</li>' . "\n";
            }
            
            // تطبيق تحديث القائمة
            $index_content = preg_replace(
                '/<aside class="doc__nav">\s*<ul>(.*?)<\/ul>\s*<\/aside>/s',
                '<aside class="doc__nav"><ul>' . $menuItems . '</ul></aside>',
                $index_content
            );
            
            // تحديث وصف الصفحة
            $description = '
            <section class="js-section">
                <h3 class="section__title">' . $data['pageDescription']['title'] . '</h3>
                <p>' . $data['pageDescription']['content'] . '</p>
            </section>';
            
            // تحديث الأقسام
            $sections = $description; // نبدأ بوصف الصفحة
            foreach ($data['sections'] as $section) {
                $sections .= '
                <section class="js-section" id="' . $section['id'] . '">
                    <h3 class="section__title">' . $section['content']['title'] . '</h3>
                    <p>' . $section['content']['description'] . '</p>';
                
                if (!empty($section['content']['steps'])) {
                    $sections .= '
                    <div class="code__block code__block--notabs">
                        <pre class="code code--block">
                            <code>';
                    foreach ($section['content']['steps'] as $step) {
                        $sections .= "                # " . htmlspecialchars($step) . "\n";
                    }
                    $sections .= '
                            </code>
                        </pre>
                    </div>';
                }
                
                $sections .= '
                </section>';
            }
            
            // تحديث التذييل
            $footer = $data['footer']['text'] . ' <a href="' . $data['footer']['link']['url'] . 
                     '" target="_blank" class="link link--light">' . $data['footer']['link']['text'] . '</a>';
            
            // تطبيق التغييرات على المحتوى
            $index_content = preg_replace(
                '/<article class="doc__content">(.*?)<\/article>/s',
                '<article class="doc__content">' . $sections . '</article>',
                $index_content
            );
            
            $index_content = preg_replace(
                '/<footer class="footer">(.*?)<\/footer>/s',
                '<footer class="footer">' . $footer . '</footer>',
                $index_content
            );

            // حفظ التغييرات على index.html في GitHub
            $post_data = [
                'message' => 'تحديث index.html',
                'content' => base64_encode($index_content),
                'sha' => $index_sha
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/$repo_owner/$repo_name/contents/index.html");
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
                echo json_encode(['success' => true]);
            } else {
                throw new Exception('فشل في تحديث index.html');
            }
        } else {
            throw new Exception('فشل في قراءة index.html');
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log('Error updating index.html: ' . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(500);
    error_log('GitHub API Error: ' . $result);
    echo json_encode(['error' => 'فشل في حفظ البيانات على GitHub']);
}
