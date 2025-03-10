<?php
// بيانات الاتصال بقاعدة البيانات
$host     = "127.0.0.1";
$dbname   = "qdwprvtpun";
$username = "qdwprvtpun";
$password = "jBvyPTS8D2";

try {
    // إنشاء اتصال باستخدام PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("فشل الاتصال: " . $e->getMessage());
}

// في حال كان النموذج قد تم إرساله
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // استلام البيانات من النموذج
    $email      = isset($_POST['email']) ? trim($_POST['email']) : '';
    $action     = isset($_POST['action']) ? $_POST['action'] : '';
    $expireDate = isset($_POST['expire_date']) ? $_POST['expire_date'] : '';

    // التحقق من صحة البريد الإلكتروني
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "بريد إلكتروني غير صالح.";
        exit;
    }

    // البحث عن المستخدم في جدول users
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "المستخدم غير موجود.";
        exit;
    }

    if ($action === 'enable') {
        // يجب التأكد من إرسال تاريخ الانتهاء في حالة التفعيل
        if (empty($expireDate)) {
            echo "يرجى تحديد تاريخ انتهاء الاشتراك.";
            exit;
        }

        // التحقق مما إذا كان يوجد سجل اشتراك مسبقاً للمستخدم
        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $user['id']]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($subscription) {
            // تحديث سجل الاشتراك
            $updateStmt = $pdo->prepare("UPDATE subscriptions SET 
                    is_active = 1, 
                    purchase_date = CURDATE(), 
                    expire_date = :expire_date, 
                    next_charge_date = :expire_date, 
                    plan_name = 'Manual Subscription',
                    updated_at = NOW()
                WHERE user_id = :user_id");
            $updateStmt->execute([
                ':expire_date' => $expireDate,
                ':user_id'     => $user['id']
            ]);
        } else {
            // إنشاء سجل اشتراك جديد للمستخدم
            $insertStmt = $pdo->prepare("INSERT INTO subscriptions 
                (user_id, is_active, purchase_date, expire_date, next_charge_date, plan_name, created_at, updated_at)
                VALUES 
                (:user_id, 1, CURDATE(), :expire_date, :expire_date, 'Manual Subscription', NOW(), NOW())");
            $insertStmt->execute([
                ':user_id'     => $user['id'],
                ':expire_date' => $expireDate
            ]);
        }

        // تحديث حالة المستخدم في جدول users ليصبح pro
        $updateUserStmt = $pdo->prepare("UPDATE users SET status = 'pro', plan_type = 'manual' WHERE id = :user_id");
        $updateUserStmt->execute([':user_id' => $user['id']]);

        echo "تم تفعيل الاشتراك للمستخدم " . htmlspecialchars($email) . " حتى تاريخ " . htmlspecialchars($expireDate) . ".";
    } elseif ($action === 'disable') {
        // تعطيل الاشتراك: تحديث سجل الاشتراك إذا موجود
        $updateStmt = $pdo->prepare("UPDATE subscriptions SET is_active = 0, updated_at = NOW() WHERE user_id = :user_id");
        $updateStmt->execute([':user_id' => $user['id']]);

        // تحديث حالة المستخدم في جدول users ليصبح free
        $updateUserStmt = $pdo->prepare("UPDATE users SET status = 'free', plan_type = NULL WHERE id = :user_id");
        $updateUserStmt->execute([':user_id' => $user['id']]);

        echo "تم تعطيل الاشتراك للمستخدم " . htmlspecialchars($email) . ".";
    } else {
        echo "إجراء غير صالح.";
    }

    exit;
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إدارة الاشتراك</title>
</head>
<body>
    <h1>إدارة الاشتراك</h1>
    <form method="post">
        <label for="email">البريد الإلكتروني للمستخدم:</label>
        <input type="email" name="email" id="email" required><br><br>

        <label>الإجراء:</label>
        <input type="radio" name="action" value="enable" id="enable" required>
        <label for="enable">تفعيل الاشتراك</label>
        <input type="radio" name="action" value="disable" id="disable">
        <label for="disable">تعطيل الاشتراك</label><br><br>

        <div id="expire_date_field" style="display: none;">
            <label for="expire_date">تاريخ انتهاء الاشتراك (YYYY-MM-DD):</label>
            <input type="date" name="expire_date" id="expire_date"><br><br>
        </div>

        <input type="submit" value="تنفيذ">
    </form>

    <script>
        // إظهار حقل تاريخ الانتهاء فقط عند اختيار تفعيل الاشتراك
        document.querySelectorAll('input[name="action"]').forEach(function(elem) {
            elem.addEventListener('change', function(event) {
                if (event.target.value === 'enable') {
                    document.getElementById('expire_date_field').style.display = 'block';
                } else {
                    document.getElementById('expire_date_field').style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
