<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$db = get_db_connection();
$results = [];

$newCategories = [
    ['name' => 'Health & Wellness', 'slug' => 'health-wellness',  'description' => 'Health and wellness tips, benefits, and lifestyle content', 'icon' => 'fa-heartbeat', 'sort_order' => 4],
    ['name' => 'Trending Topics',   'slug' => 'trending-topics',  'description' => 'Trending news, stories, and discussions',                   'icon' => 'fa-fire',      'sort_order' => 6],
    ['name' => 'Kashmir Culture',   'slug' => 'kashmir-culture',  'description' => 'Stories and traditions from the Kashmir valley',            'icon' => 'fa-mountain',  'sort_order' => 5],
    ['name' => 'Buying Guides',     'slug' => 'buying-guides',    'description' => 'Expert guides to help you buy the best products',           'icon' => 'fa-shopping-bag','sort_order' => 7],
];

foreach ($newCategories as $cat) {
    $exists = $db->prepare("SELECT id FROM blog_categories WHERE slug = ?");
    $exists->execute([$cat['slug']]);
    if ($exists->fetch()) {
        $results[] = "⚠️ Already exists: <strong>{$cat['name']}</strong>";
        continue;
    }
    $stmt = $db->prepare("INSERT INTO blog_categories (name, slug, description, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$cat['name'], $cat['slug'], $cat['description'], $cat['icon'], $cat['sort_order']]);
    $results[] = "✅ Added: <strong>{$cat['name']}</strong> (id=" . $db->lastInsertId() . ")";
}
?>
<!DOCTYPE html><html><head><title>Add Blog Categories</title>
<style>body{font-family:sans-serif;padding:40px;max-width:600px;margin:auto}
.msg{padding:12px 16px;margin:8px 0;border-radius:8px;background:#f0fdf4;border:1px solid #bbf7d0;font-size:15px}
.warn{background:#fffbeb;border-color:#fde68a}
.btn{display:inline-block;margin-top:20px;padding:10px 20px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;font-size:14px}
</style></head><body>
<h2>Blog Categories — Migration</h2>
<?php foreach ($results as $r): ?>
    <div class="msg <?= str_contains($r, '⚠️') ? 'warn' : '' ?>"><?= $r ?></div>
<?php endforeach; ?>
<a class="btn" href="manage_blogs.php">← Back to Manage Blogs</a>
<p style="margin-top:20px;color:#999;font-size:13px">⚠️ Delete this file from the server after running it.</p>
</body></html>
