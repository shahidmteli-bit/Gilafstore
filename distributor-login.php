<?php
/**
 * Distributor Login Handler
 * =========================
 * Completely independent from admin authentication.
 * Authenticates distributors against the distributors table ONLY.
 * Does NOT touch admin sessions, admin tables, or admin routes.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

// Auto-create distributors table if not exists (future-ready)
try {
    $db = get_db_connection();
    $db->exec("CREATE TABLE IF NOT EXISTS distributors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        distributor_id VARCHAR(20) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        phone VARCHAR(20) DEFAULT NULL,
        password_hash VARCHAR(255) NOT NULL,
        company_name VARCHAR(255) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        status ENUM('active','inactive','suspended') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_distributor_id (distributor_id),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    error_log("Distributor table creation error: " . $e->getMessage());
}

$errors = [];
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($identifier)) {
        $errors[] = 'Please enter your Distributor ID or Email.';
    }
    if (empty($password)) {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        // Rate limiting — prevent brute force
        $ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
        $rateLimitKey = 'distributor_login_' . $ip;

        $isLimited = false;
        if (function_exists('check_rate_limit')) {
            $isLimited = !check_rate_limit($rateLimitKey, 5, 900); // 5 attempts per 15 min
        }

        if ($isLimited) {
            $errors[] = 'Too many login attempts. Please try again in 15 minutes.';
        } else {
            // Authenticate against distributors table ONLY
            try {
                $db = get_db_connection();
                $stmt = $db->prepare("SELECT * FROM distributors 
                                      WHERE (distributor_id = :id OR email = :email) 
                                      AND status = 'active' 
                                      LIMIT 1");
                $stmt->execute([':id' => $identifier, ':email' => $identifier]);
                $distributor = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($distributor && password_verify($password, $distributor['password_hash'])) {
                    // Successful login — set distributor session (NOT admin session)
                    $_SESSION['distributor'] = [
                        'id' => $distributor['id'],
                        'distributor_id' => $distributor['distributor_id'],
                        'name' => $distributor['name'],
                        'email' => $distributor['email'],
                        'company_name' => $distributor['company_name']
                    ];

                    // Log successful login
                    if (function_exists('security_log')) {
                        security_log('DISTRIBUTOR_LOGIN_SUCCESS', 'INFO', 
                            "Distributor {$distributor['distributor_id']} logged in", [
                                'distributor_id' => $distributor['distributor_id'],
                                'ip' => $ip
                            ]);
                    }

                    // Redirect to distributor dashboard (future)
                    header('Location: ' . base_url('distributor-portal.php'));
                    exit;
                } else {
                    $errors[] = 'Invalid Distributor ID/Email or password.';

                    // Log failed attempt
                    if (function_exists('security_log')) {
                        security_log('DISTRIBUTOR_LOGIN_FAILED', 'WARNING', 
                            "Failed distributor login attempt for: {$identifier}", [
                                'identifier' => $identifier,
                                'ip' => $ip
                            ]);
                    }
                }
            } catch (PDOException $e) {
                // Table might not have any data yet
                $errors[] = 'Invalid Distributor ID/Email or password.';
                error_log("Distributor login error: " . $e->getMessage());
            }
        }
    }
}

// Show error page with redirect back
$pageTitle = 'Distributor Login — Gilaf Store';
require_once __DIR__ . '/includes/new-header.php';
?>

<style>
.dist-login-error-container {
    max-width: 480px;
    margin: 60px auto;
    padding: 40px 30px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    text-align: center;
}
.dist-login-error-container .icon-circle {
    width: 64px;
    height: 64px;
    background: #FFF3F3;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}
.dist-login-error-container .icon-circle i {
    font-size: 26px;
    color: #C62828;
}
.dist-login-error-container h2 {
    font-family: var(--font-serif, 'Playfair Display', serif);
    color: #1A3C34;
    margin-bottom: 12px;
    font-size: 1.4rem;
}
.dist-login-error-container .error-list {
    background: #FFF3F3;
    border: 1px solid #FFCDD2;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 24px;
    text-align: left;
}
.dist-login-error-container .error-list li {
    color: #C62828;
    font-size: 0.88rem;
    margin-bottom: 4px;
    list-style: none;
}
.dist-login-error-container .error-list li:before {
    content: "• ";
    color: #C62828;
}
.dist-login-error-container .btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    background: #1A3C34;
    color: white;
    border-radius: 10px;
    font-size: 0.92rem;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s;
}
.dist-login-error-container .btn-back:hover {
    background: #2C5530;
}
.dist-login-error-container .helper-text {
    margin-top: 16px;
    font-size: 0.82rem;
    color: #999;
}
.dist-login-error-container .helper-text a {
    color: #1A3C34;
    font-weight: 600;
    text-decoration: none;
}
</style>

<div class="dist-login-error-container">
    <div class="icon-circle">
        <i class="fas fa-exclamation-triangle"></i>
    </div>
    <h2>Login Failed</h2>
    
    <?php if (!empty($errors)): ?>
        <ul class="error-list">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <a href="javascript:history.back()" class="btn-back">
        <i class="fas fa-arrow-left"></i> Try Again
    </a>
    
    <p class="helper-text">
        Don't have an account? <a href="<?= base_url('apply-distributor.php') ?>">Apply to become a distributor</a>
    </p>
</div>

<?php require_once __DIR__ . '/includes/new-footer.php'; ?>
