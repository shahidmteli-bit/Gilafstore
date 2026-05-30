<?php
/**
 * One-time migration: Creates footer_social_media table, seeds platforms,
 * and adds footer-specific columns to company_profile.
 * Run once from admin panel, then keep for future reference.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_admin();

$log  = [];
$db   = get_db_connection();

// ─── 1. Create footer_social_media table ──────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `footer_social_media` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `platform_name` VARCHAR(60)  NOT NULL,
        `icon_class`    VARCHAR(120) NOT NULL DEFAULT '',
        `icon_color`    VARCHAR(20)  NOT NULL DEFAULT '#ffffff',
        `social_url`    VARCHAR(500) DEFAULT '',
        `is_enabled`    TINYINT(1)   DEFAULT 0,
        `open_new_tab`  TINYINT(1)   DEFAULT 1,
        `sort_order`    INT          DEFAULT 0,
        `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $log[] = ['ok', 'Table <code>footer_social_media</code> created / verified.'];
} catch (PDOException $e) {
    $log[] = ['err', 'Table creation failed: ' . htmlspecialchars($e->getMessage())];
}

// ─── 2. Seed default platforms (only if table is empty) ───────────────────
try {
    $existing = (int)$db->query("SELECT COUNT(*) FROM footer_social_media")->fetchColumn();
    if ($existing === 0) {
        $platforms = [
            ['Facebook',    'fab fa-facebook-f',     '#1877F2', '', 0, 1, 1],
            ['Instagram',   'fab fa-instagram',      '#E1306C', '', 0, 1, 2],
            ['WhatsApp',    'fab fa-whatsapp',       '#25D366', '', 0, 1, 3],
            ['LinkedIn',    'fab fa-linkedin-in',    '#0A66C2', '', 0, 1, 4],
            ['YouTube',     'fab fa-youtube',        '#FF0000', '', 0, 1, 5],
            ['X / Twitter', 'fab fa-twitter',        '#1DA1F2', '', 0, 1, 6],
            ['Telegram',    'fab fa-telegram-plane', '#2CA5E0', '', 0, 1, 7],
            ['Pinterest',   'fab fa-pinterest-p',    '#E60023', '', 0, 1, 8],
            ['Snapchat',    'fab fa-snapchat-ghost', '#FFFC00', '', 0, 1, 9],
            ['Threads',     'fab fa-threads',        '#000000', '', 0, 1, 10],
        ];
        $stmt = $db->prepare(
            "INSERT INTO footer_social_media
             (platform_name, icon_class, icon_color, social_url, is_enabled, open_new_tab, sort_order)
             VALUES (?,?,?,?,?,?,?)"
        );
        foreach ($platforms as $p) {
            $stmt->execute($p);
        }
        $log[] = ['ok', '10 social media platforms seeded successfully.'];
    } else {
        $log[] = ['ok', "Table already has <strong>{$existing}</strong> platforms — seed skipped."];
    }
} catch (PDOException $e) {
    $log[] = ['err', 'Seeding failed: ' . htmlspecialchars($e->getMessage())];
}

// ─── 3. Add footer-specific columns to company_profile ────────────────────
$newCols = [
    'footer_description'   => "VARCHAR(500)  NOT NULL DEFAULT ''",
    'footer_reg_address'   => "VARCHAR(500)  NOT NULL DEFAULT ''",
    'footer_reg_city'      => "VARCHAR(100)  NOT NULL DEFAULT ''",
    'footer_reg_state'     => "VARCHAR(100)  NOT NULL DEFAULT ''",
    'footer_reg_pincode'   => "VARCHAR(10)   NOT NULL DEFAULT ''",
    'footer_reg_country'   => "VARCHAR(100)  NOT NULL DEFAULT 'India'",
    'footer_support_email' => "VARCHAR(150)  NOT NULL DEFAULT ''",
    'footer_phone_display' => "VARCHAR(30)   NOT NULL DEFAULT ''",
    'footer_whatsapp'      => "VARCHAR(30)   NOT NULL DEFAULT ''",
];

foreach ($newCols as $col => $definition) {
    try {
        $exists = $db->query("SHOW COLUMNS FROM company_profile LIKE '{$col}'")->rowCount();
        if ($exists === 0) {
            $db->exec("ALTER TABLE company_profile ADD COLUMN `{$col}` {$definition}");
            $log[] = ['ok', "Added column <code>company_profile.{$col}</code>"];
        } else {
            $log[] = ['ok', "Column <code>company_profile.{$col}</code> already exists — skipped."];
        }
    } catch (PDOException $e) {
        $log[] = ['err', "Column <code>{$col}</code>: " . htmlspecialchars($e->getMessage())];
    }
}

$pageTitle = 'Footer Social Migration — Admin';
include __DIR__ . '/../includes/admin_header.php';
?>
<section class="py-4">
<div class="container-fluid" style="max-width:700px;">
    <h4 class="fw-semibold mb-3"><i class="fas fa-database text-primary me-2"></i>Footer Social Media — Migration Results</h4>
    <?php foreach ($log as [$type, $msg]): ?>
    <div class="alert alert-<?= $type === 'ok' ? 'success' : 'danger'; ?> py-2 mb-2">
        <i class="fas fa-<?= $type === 'ok' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?= $msg; ?>
    </div>
    <?php endforeach; ?>
    <a href="company_profile.php" class="btn btn-primary mt-3"><i class="fas fa-arrow-left me-2"></i>Back to Company Profile</a>
</div>
</section>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
