<?php
/**
 * Distributor Portal — Coming Soon Landing Page
 * ==============================================
 * Fully independent from admin authentication.
 * Collects interest emails in distributor_interest_emails table.
 */
require_once __DIR__ . '/includes/functions.php';

$success = false;
$error = '';

// Auto-create table if not exists
try {
    $db = get_db_connection();
    $db->exec("CREATE TABLE IF NOT EXISTS distributor_interest_emails (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        name VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    error_log("Distributor table creation error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $db = get_db_connection();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $stmt = $db->prepare("INSERT INTO distributor_interest_emails (email, name, phone, ip_address) 
                                  VALUES (:email, :name, :phone, :ip)
                                  ON DUPLICATE KEY UPDATE name = VALUES(name), phone = VALUES(phone)");
            $stmt->execute([
                ':email' => $email,
                ':name' => $name ?: null,
                ':phone' => $phone ?: null,
                ':ip' => $ip
            ]);
            $success = true;
        } catch (Exception $e) {
            error_log("Distributor interest email error: " . $e->getMessage());
            $error = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Distributor Portal — Gilaf Store';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green: #1A3C34;
            --green-light: #2C5530;
            --gold: #C5A059;
            --gold-light: #D4B06A;
            --cream: #FAF8F5;
            --text: #333;
            --text-light: #777;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Header Bar ── */
        .dist-header {
            background: var(--green);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dist-header a {
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
            opacity: 0.85;
            transition: opacity 0.2s;
        }
        .dist-header a:hover { opacity: 1; }
        .dist-logo {
            font-family: 'Playfair Display', serif;
            color: white;
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .dist-logo span { color: var(--gold); }

        /* ── Hero Section ── */
        .dist-hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .dist-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.08);
            max-width: 560px;
            width: 100%;
            overflow: hidden;
        }

        /* ── Card Top ── */
        .dist-card-top {
            background: linear-gradient(135deg, var(--green) 0%, var(--green-light) 100%);
            padding: 48px 40px 40px;
            text-align: center;
            position: relative;
        }
        .dist-card-top::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 30px;
            background: white;
            border-radius: 20px 20px 0 0;
        }
        .dist-icon {
            width: 72px;
            height: 72px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 2px solid rgba(255,255,255,0.2);
        }
        .dist-icon i {
            font-size: 28px;
            color: var(--gold);
        }
        .dist-card-top h1 {
            font-family: 'Playfair Display', serif;
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .dist-card-top p {
            color: rgba(255,255,255,0.75);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* ── Badge ── */
        .coming-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gold);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .coming-badge .dot {
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* ── Card Body ── */
        .dist-card-body {
            padding: 10px 40px 40px;
        }
        .dist-card-body h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            color: var(--green);
            margin-bottom: 6px;
            text-align: center;
        }
        .dist-card-body > p {
            color: var(--text-light);
            font-size: 0.88rem;
            text-align: center;
            margin-bottom: 28px;
            line-height: 1.5;
        }

        /* ── Features ── */
        .dist-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 28px;
        }
        .dist-feat {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: var(--cream);
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text);
        }
        .dist-feat i {
            color: var(--gold);
            font-size: 14px;
            width: 16px;
            text-align: center;
            flex-shrink: 0;
        }

        /* ── Form ── */
        .dist-form { margin-top: 4px; }
        .dist-form label {
            display: block;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 5px;
        }
        .dist-form input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #E0E0E0;
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
            margin-bottom: 14px;
            background: #FAFAFA;
        }
        .dist-form input:focus {
            outline: none;
            border-color: var(--green);
            background: white;
        }
        .dist-form button {
            width: 100%;
            padding: 13px;
            background: var(--green);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .dist-form button:hover { background: var(--green-light); }
        .dist-form button:active { transform: scale(0.98); }
        .dist-form .helper {
            font-size: 0.78rem;
            color: var(--text-light);
            text-align: center;
            margin-top: 12px;
        }

        /* ── Success State ── */
        .success-box {
            text-align: center;
            padding: 20px 0;
        }
        .success-icon {
            width: 64px;
            height: 64px;
            background: #E8F5E9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
        .success-icon i { font-size: 28px; color: #2E7D32; }
        .success-box h3 {
            font-family: 'Playfair Display', serif;
            color: var(--green);
            font-size: 1.2rem;
            margin-bottom: 8px;
        }
        .success-box p {
            color: var(--text-light);
            font-size: 0.88rem;
            line-height: 1.5;
        }
        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 20px;
            padding: 10px 24px;
            background: var(--cream);
            color: var(--green);
            border: 1.5px solid var(--green);
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-home:hover { background: var(--green); color: white; }

        /* ── Error ── */
        .error-msg {
            background: #FFF3F3;
            color: #C62828;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 16px;
            border: 1px solid #FFCDD2;
        }

        /* ── Footer ── */
        .dist-footer {
            text-align: center;
            padding: 20px;
            color: var(--text-light);
            font-size: 0.8rem;
        }
        .dist-footer a { color: var(--green); text-decoration: none; font-weight: 500; }

        /* ── Mobile ── */
        @media (max-width: 600px) {
            .dist-card-top { padding: 36px 24px 32px; }
            .dist-card-top h1 { font-size: 1.5rem; }
            .dist-card-body { padding: 10px 24px 32px; }
            .dist-features { grid-template-columns: 1fr; }
            .dist-hero { padding: 20px 16px; }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="dist-header">
    <a href="<?= base_url('/') ?>"><i class="fas fa-arrow-left"></i> Back to Store</a>
    <div class="dist-logo">Gilaf<span>Store</span></div>
    <a href="<?= base_url('apply-distributor.php') ?>"><i class="fas fa-file-alt"></i> Apply Now</a>
</div>

<!-- Main Content -->
<div class="dist-hero">
    <div class="dist-card">
        <!-- Card Top -->
        <div class="dist-card-top">
            <div class="coming-badge"><div class="dot"></div> Coming Soon</div>
            <div class="dist-icon"><i class="fas fa-store"></i></div>
            <h1>Distributor Portal</h1>
            <p>Your dedicated platform for bulk orders, wholesale pricing, and business management.</p>
        </div>

        <!-- Card Body -->
        <div class="dist-card-body">
            <?php if ($success): ?>
                <!-- Success State -->
                <div class="success-box">
                    <div class="success-icon"><i class="fas fa-check"></i></div>
                    <h3>You're on the list!</h3>
                    <p>We'll notify you at <strong><?= htmlspecialchars($_POST['email'] ?? '') ?></strong> as soon as the Distributor Portal launches.</p>
                    <a href="<?= base_url('/') ?>" class="btn-home"><i class="fas fa-home"></i> Back to Store</a>
                </div>
            <?php else: ?>
                <h2>Get Notified When We Launch</h2>
                <p>We're building something great for our distribution partners. Leave your details and be the first to know.</p>

                <!-- Features -->
                <div class="dist-features">
                    <div class="dist-feat"><i class="fas fa-tags"></i> Wholesale Pricing</div>
                    <div class="dist-feat"><i class="fas fa-box-open"></i> Bulk Orders</div>
                    <div class="dist-feat"><i class="fas fa-chart-line"></i> Sales Dashboard</div>
                    <div class="dist-feat"><i class="fas fa-headset"></i> Priority Support</div>
                </div>

                <?php if ($error): ?>
                    <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Form -->
                <form method="post" action="" class="dist-form">
                    <label for="dist-name">Full Name</label>
                    <input type="text" id="dist-name" name="name" placeholder="Your full name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

                    <label for="dist-email">Email Address <span style="color:#C62828">*</span></label>
                    <input type="email" id="dist-email" name="email" placeholder="you@company.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

                    <label for="dist-phone">Phone Number</label>
                    <input type="tel" id="dist-phone" name="phone" placeholder="+91 XXXXX XXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

                    <button type="submit"><i class="fas fa-bell"></i> Notify Me</button>
                    <p class="helper">We respect your privacy. No spam, ever.</p>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="dist-footer">
    <p>&copy; <?= date('Y') ?> Gilaf Store. All rights reserved. | <a href="<?= base_url('apply-distributor.php') ?>">Apply to become a distributor</a></p>
</div>

</body>
</html>
