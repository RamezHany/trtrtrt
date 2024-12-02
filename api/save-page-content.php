<?php
header('Content-Type: application/json');

// تلقي البيانات
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data) {
    // حفظ البيانات في ملف JSON
    $result = file_put_contents('content/page-content.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if ($result !== false) {
        // تحديث ملف index.html
        $indexContent = file_get_contents('index.html');
        
        // تحديث الشعار
        $indexContent = preg_replace(
            '/<img src="[^"]*"/',
            '<img src="' . $data['navigation']['logo'] . '"',
            $indexContent
        );
        
        // تحديث رابط GitHub
        $indexContent = preg_replace(
            '/href="https:\/\/github\.com[^"]*"/',
            'href="' . $data['navigation']['github_link'] . '"',
            $indexContent
        );
        
        // تحديث القائمة الجانبية
        $menuItems = '';
        foreach ($data['navigation']['menu_items'] as $item) {
            $menuItems .= '<li class="js-btn" data-section="' . $item['id'] . '">' . $item['title'] . '</li>' . "\n";
        }
        
        // تطبيق تحديث القائمة
        $indexContent = preg_replace(
            '/<aside class="doc__nav">\s*<ul>(.*?)<\/ul>\s*<\/aside>/s',
            '<aside class="doc__nav"><ul>' . $menuItems . '</ul></aside>',
            $indexContent
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
        
        // تطبيق التغييرات على ملف index.html
        $indexContent = preg_replace(
            '/<article class="doc__content">(.*?)<\/article>/s',
            '<article class="doc__content">' . $sections . '</article>',
            $indexContent
        );
        
        $indexContent = preg_replace(
            '/<footer class="footer">(.*?)<\/footer>/s',
            '<footer class="footer">' . $footer . '</footer>',
            $indexContent
        );
        
        // حفظ التغييرات
        file_put_contents('index.html', $indexContent);
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'فشل في حفظ البيانات. يرجى المحاولة مرة أخرى لاحقًا.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'بيانات غير صالحة. تأكد من صحة البيانات المدخلة.']);
}
?>
