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

// عند إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // استلام البيانات الأساسية
    $email  = isset($_POST['email']) ? trim($_POST['email']) : '';
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
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
        // استلام الحقول الخاصة بجدول subscriptions
        $expireDate             = isset($_POST['expire_date']) ? $_POST['expire_date'] : '';
        $subscription_id        = isset($_POST['subscription_id']) ? $_POST['subscription_id'] : '';
        $plan_id                = isset($_POST['plan_id']) ? $_POST['plan_id'] : '';
        $plan_name              = isset($_POST['plan_name']) ? $_POST['plan_name'] : '';
        $customer_country       = isset($_POST['customer_country']) ? $_POST['customer_country'] : '';
        $customer_currency_code = isset($_POST['customer_currency_code']) ? $_POST['customer_currency_code'] : '';
        $customer_charge_amount = isset($_POST['customer_charge_amount']) ? $_POST['customer_charge_amount'] : '';
        $payment_method         = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
        $payer_email            = isset($_POST['payer_email']) ? $_POST['payer_email'] : '';
        $invoice_link           = isset($_POST['invoice_link']) ? $_POST['invoice_link'] : '';
        $renew_count            = isset($_POST['renew_count']) ? $_POST['renew_count'] : '';
        $modal                  = isset($_POST['modal']) ? $_POST['modal'] : '';
        // الحقول الإضافية لتحديث جدول users
        $plan_type              = isset($_POST['plan_type']) ? $_POST['plan_type'] : '';
        $credits                = isset($_POST['credits']) ? $_POST['credits'] : '';
        $conversion_count       = isset($_POST['conversion_count']) ? $_POST['conversion_count'] : '';
        
        // التحقق من تعبئة جميع الحقول المطلوبة لتفعيل الاشتراك
        if (empty($expireDate) || empty($subscription_id) || empty($plan_id) || empty($plan_name) ||
            empty($customer_country) || empty($customer_currency_code) || $customer_charge_amount === '' ||
            empty($payment_method) || empty($payer_email) || empty($invoice_link) || $renew_count === '' ||
            empty($modal) || empty($plan_type) || $credits === '' || $conversion_count === '') {
            echo "يرجى تعبئة جميع الحقول الخاصة بتفعيل الاشتراك.";
            exit;
        }
        
        // التحقق مما إذا كان يوجد سجل اشتراك مسبقاً للمستخدم
        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $user['id']]);
        $subscriptionRec = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subscriptionRec) {
            // تحديث سجل الاشتراك للمستخدم
            $updateStmt = $pdo->prepare("
                UPDATE subscriptions SET
                    subscription_id         = :subscription_id,
                    plan_id                 = :plan_id,
                    plan_name               = :plan_name,
                    customer_country        = :customer_country,
                    customer_currency_code  = :customer_currency_code,
                    customer_charge_amount  = :customer_charge_amount,
                    payment_method          = :payment_method,
                    payer_email             = :payer_email,
                    invoice_link            = :invoice_link,
                    renew_count             = :renew_count,
                    modal                   = :modal,
                    is_active               = 1,
                    purchase_date           = CURDATE(),
                    expire_date             = :expire_date,
                    next_charge_date        = :expire_date,
                    updated_at              = NOW()
                WHERE user_id = :user_id
            ");
            $updateStmt->execute([
                ':subscription_id'         => $subscription_id,
                ':plan_id'                 => $plan_id,
                ':plan_name'               => $plan_name,
                ':customer_country'        => $customer_country,
                ':customer_currency_code'  => $customer_currency_code,
                ':customer_charge_amount'  => $customer_charge_amount,
                ':payment_method'          => $payment_method,
                ':payer_email'             => $payer_email,
                ':invoice_link'            => $invoice_link,
                ':renew_count'             => $renew_count,
                ':modal'                   => $modal,
                ':expire_date'             => $expireDate,
                ':user_id'                 => $user['id']
            ]);
        } else {
            // إنشاء سجل اشتراك جديد للمستخدم
            $insertStmt = $pdo->prepare("
                INSERT INTO subscriptions (
                    user_id,
                    subscription_id,
                    plan_id,
                    plan_name,
                    customer_country,
                    customer_currency_code,
                    customer_charge_amount,
                    payment_method,
                    payer_email,
                    invoice_link,
                    renew_count,
                    modal,
                    is_active,
                    purchase_date,
                    expire_date,
                    next_charge_date,
                    created_at,
                    updated_at
                )
                VALUES (
                    :user_id,
                    :subscription_id,
                    :plan_id,
                    :plan_name,
                    :customer_country,
                    :customer_currency_code,
                    :customer_charge_amount,
                    :payment_method,
                    :payer_email,
                    :invoice_link,
                    :renew_count,
                    :modal,
                    1,
                    CURDATE(),
                    :expire_date,
                    :expire_date,
                    NOW(),
                    NOW()
                )
            ");
            $insertStmt->execute([
                ':user_id'                 => $user['id'],
                ':subscription_id'         => $subscription_id,
                ':plan_id'                 => $plan_id,
                ':plan_name'               => $plan_name,
                ':customer_country'        => $customer_country,
                ':customer_currency_code'  => $customer_currency_code,
                ':customer_charge_amount'  => $customer_charge_amount,
                ':payment_method'          => $payment_method,
                ':payer_email'             => $payer_email,
                ':invoice_link'            => $invoice_link,
                ':renew_count'             => $renew_count,
                ':modal'                   => $modal,
                ':expire_date'             => $expireDate
            ]);
        }
        
        // تحديث حالة المستخدم في جدول users إلى pro وتحديث الحقول الإضافية
        $updateUserStmt = $pdo->prepare("UPDATE users SET status = 'pro', plan_type = :plan_type, credits = :credits, conversion_count = :conversion_count WHERE id = :user_id");
        $updateUserStmt->execute([
            ':plan_type'         => $plan_type,
            ':credits'           => $credits,
            ':conversion_count'  => $conversion_count,
            ':user_id'           => $user['id']
        ]);
        
        echo "تم تفعيل الاشتراك للمستخدم " . htmlspecialchars($email) . " حتى تاريخ " . htmlspecialchars($expireDate) . ".";
    } elseif ($action === 'disable') {
        // عند تعطيل الاشتراك، لا نحتاج إلى تعبئة الحقول الخاصة بالتفعيل
        // حذف سجل الاشتراك من جدول subscriptions إن وُجد
        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $user['id']]);
        $subscriptionRec = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subscriptionRec) {
            $deleteStmt = $pdo->prepare("DELETE FROM subscriptions WHERE user_id = :user_id");
            $deleteStmt->execute([':user_id' => $user['id']]);
        }
        
        // تحديث حالة المستخدم في جدول users إلى free وتعيين plan_type إلى NULL (يمكنك تعديل credits أو conversion_count إذا أردت)
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
    <style>
        label { display: block; margin: 5px 0; }
        .group { border: 1px solid #ccc; padding: 10px; margin-top: 10px; }
    </style>
</head>
<body>
    <h1>إدارة الاشتراك</h1>
    <form method="post">
        <label for="email">البريد الإلكتروني للمستخدم:</label>
        <input type="email" name="email" id="email" required>
        
        <br>
        <label>الإجراء:</label>
        <input type="radio" name="action" value="enable" id="enable" required>
        <label for="enable">تفعيل الاشتراك</label>
        <input type="radio" name="action" value="disable" id="disable">
        <label for="disable">تعطيل الاشتراك</label>
        
        <!-- حقول تفعيل الاشتراك ستظهر فقط عند اختيار enable -->
        <div id="enable_fields" class="group" style="display: none;">
            <h3>بيانات تفعيل الاشتراك</h3>
            
            <label for="expire_date">تاريخ انتهاء الاشتراك (YYYY-MM-DD):</label>
            <input type="date" name="expire_date" id="expire_date">
            
            <label for="subscription_id">Subscription ID:</label>
            <input type="text" name="subscription_id" id="subscription_id">
            
            <label for="plan_id">Plan ID:</label>
            <input type="text" name="plan_id" id="plan_id">
            
            <label for="plan_name">Plan Name:</label>
            <input type="text" name="plan_name" id="plan_name">
            
            <label for="customer_country">Customer Country:</label>
            <input type="text" name="customer_country" id="customer_country">
            
            <label for="customer_currency_code">Customer Currency Code:</label>
            <input type="text" name="customer_currency_code" id="customer_currency_code">
            
            <label for="customer_charge_amount">Customer Charge Amount:</label>
            <input type="number" step="0.01" name="customer_charge_amount" id="customer_charge_amount">
            
            <label for="payment_method">Payment Method:</label>
            <input type="text" name="payment_method" id="payment_method">
            
            <label for="payer_email">Payer Email:</label>
            <input type="email" name="payer_email" id="payer_email">
            
            <label for="invoice_link">Invoice Link:</label>
            <input type="text" name="invoice_link" id="invoice_link">
            
            <label for="renew_count">Renew Count:</label>
            <input type="number" name="renew_count" id="renew_count">
            
            <label for="modal">Modal:</label>
            <input type="text" name="modal" id="modal">
            
            <!-- الحقول الإضافية لتحديث جدول users -->
            <h3>بيانات المستخدم الإضافية</h3>
            <label for="plan_type">Plan Type:</label>
            <input type="text" name="plan_type" id="plan_type">
            
            <label for="credits">Credits:</label>
            <input type="number" name="credits" id="credits">
            
            <label for="conversion_count">Conversion Count:</label>
            <input type="number" name="conversion_count" id="conversion_count">
        </div>
        
        <br>
        <input type="submit" value="تنفيذ">
    </form>
    
    <script>
        // عند تغيير اختيار الإجراء، يتم تفعيل أو تعطيل الحقول الخاصة بالتفعيل
        document.querySelectorAll('input[name="action"]').forEach(function(elem) {
            elem.addEventListener('change', function(event) {
                var enableDiv = document.getElementById('enable_fields');
                if (event.target.value === 'enable') {
                    enableDiv.style.display = 'block';
                    // تأكيد تفعيل الخاصية required للحقول داخل enable_fields
                    enableDiv.querySelectorAll('input').forEach(function(input) {
                        input.disabled = false;
                        input.setAttribute('required', 'required');
                    });
                } else {
                    enableDiv.style.display = 'none';
                    // عند اختيار تعطيل الاشتراك، قم بتعطيل الحقول وإزالة required
                    enableDiv.querySelectorAll('input').forEach(function(input) {
                        input.disabled = true;
                        input.removeAttribute('required');
                    });
                }
            });
        });
    </script>
</body>
</html>
