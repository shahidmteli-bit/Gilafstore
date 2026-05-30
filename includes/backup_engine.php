<?php
/**
 * Backup Engine
 * ─────────────────────────────────────────────────────────
 * Core system: compression (ZIP), deduplication (SHA-256),
 * incremental diffs, database dumps, restore chains,
 * auto-cleanup retention, checksum verification.
 *
 * Hybrid: works on both Windows (XAMPP) and Linux (Hostinger).
 * Background execution via CLI (backup_cron.php) or spawned from web UI.
 */
class BackupEngine
{
    private PDO $db;
    private string $projectRoot;
    private string $backupRoot;
    private string $cssDir;
    private string $mysqldump;
    private string $mysqlBin;
    private bool $isWindows;

    const STATIC_EXT = [
        'jpg','jpeg','png','gif','webp','svg','ico','bmp','heic','heif',
        'mp4','mp3','wav','ogg','webm','avi','mov',
        'pdf','doc','docx','xls','xlsx','ppt','pptx',
        'zip','rar','gz','tar','7z',
        'woff','woff2','ttf','eot','otf'
    ];

    const EXCLUDE_DIRS  = ['.git','node_modules','vendor','.windsurf-backups','.windsurf','.vscode'];
    const EXCLUDE_FILES = ['.DS_Store','Thumbs.db','desktop.ini'];

    const LAYOUT_MAP = [
        'desktop' => [
            'css' => ['new-design.css','style.css','premium-design-system.css','navigation-separation.css',
                      'shop-page.css','shop-page-fixes.css','shop-ads-panel.css','product-detail-page.css',
                      'product-layout-enhanced.css','product-card-button-fixes.css','cart-page.css',
                      'checkout-page.css','premium-checkout.css','cinematic-cart-checkout.css',
                      'modern-cart-checkout.css','login-premium.css','signup-page.css','chatbot.css',
                      'admin-premium.css','layout-fixes.css','adaptive-structure.css'],
            'php' => ['includes/new-header.php','includes/new-footer.php']
        ],
        'tablet' => [
            'css' => ['tablet-layout-fixes.css','layout-fixes.css','adaptive-structure.css'],
            'php' => ['includes/new-header.php','includes/new-footer.php']
        ],
        'mobile' => [
            'css' => ['mobile-nav.css','mobile-menu-redesign.css','mobile-responsive.css',
                      'mobile-section-titles.css','mobile-tagline-fix.css','mobile-homepage-fix.css',
                      'mobile-zero-truncation.css','layout-fixes.css','adaptive-structure.css'],
            'php' => ['includes/new-header.php','includes/new-footer.php']
        ]
    ];

    const RETENTION = ['daily' => 7, 'weekly' => 4, 'monthly' => 6];

    // ═══════════════════════════════════════════════════════
    // CONSTRUCTOR
    // ═══════════════════════════════════════════════════════
    public function __construct(PDO $db)
    {
        $this->db          = $db;
        $this->projectRoot = realpath(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
        $this->backupRoot  = $this->projectRoot . '.windsurf-backups' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR;
        $this->cssDir      = $this->projectRoot . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR;
        $this->isWindows   = (PHP_OS_FAMILY === 'Windows');
        $this->mysqldump   = $this->findBinary('mysqldump');
        $this->mysqlBin    = $this->findBinary('mysql');

        foreach (['full','incremental'] as $d) {
            $p = $this->backupRoot . $d;
            if (!is_dir($p)) @mkdir($p, 0755, true);
        }
        $this->ensureTables();
    }

    // ═══════════════════════════════════════════════════════
    // DATABASE SCHEMA
    // ═══════════════════════════════════════════════════════
    private function ensureTables(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS backup_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            backup_id VARCHAR(100) NOT NULL UNIQUE,
            backup_name VARCHAR(200) NOT NULL,
            description TEXT,
            admin_user VARCHAR(100) NOT NULL,
            backup_type ENUM('full','incremental','restore','pre_restore','manual') DEFAULT 'full',
            parent_backup_id VARCHAR(100),
            file_count INT DEFAULT 0,
            files_changed INT DEFAULT 0,
            files_added INT DEFAULT 0,
            files_deleted INT DEFAULT 0,
            size_bytes BIGINT DEFAULT 0,
            size_compressed BIGINT DEFAULT 0,
            backup_path VARCHAR(500) NOT NULL,
            checksum VARCHAR(64),
            status ENUM('active','restored','archived','failed','in_progress') DEFAULT 'active',
            is_restore TINYINT(1) DEFAULT 0,
            restored_from VARCHAR(100),
            includes_database TINYINT(1) DEFAULT 1,
            lock_state_json TEXT,
            retention_policy VARCHAR(20) DEFAULT 'daily',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->exec("CREATE TABLE IF NOT EXISTS backup_file_manifest (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            backup_id VARCHAR(100) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_hash VARCHAR(64) NOT NULL,
            file_size BIGINT DEFAULT 0,
            modified_at INT DEFAULT 0,
            storage_type ENUM('stored','reference') DEFAULT 'stored',
            reference_backup_id VARCHAR(100),
            segment ENUM('static','dynamic') DEFAULT 'dynamic',
            INDEX idx_bfm_backup (backup_id),
            INDEX idx_bfm_hash (file_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->exec("CREATE TABLE IF NOT EXISTS layout_locks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_category ENUM('desktop','tablet','mobile') NOT NULL UNIQUE,
            is_locked TINYINT(1) DEFAULT 0,
            locked_by VARCHAR(100),
            locked_at TIMESTAMP NULL,
            unlocked_by VARCHAR(100),
            unlocked_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->exec("CREATE TABLE IF NOT EXISTS layout_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(50) NOT NULL,
            device_category VARCHAR(20),
            backup_id VARCHAR(100),
            admin_user VARCHAR(100) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        foreach (['desktop','tablet','mobile'] as $c) {
            $this->db->prepare("INSERT IGNORE INTO layout_locks (device_category,is_locked) VALUES (?,0)")->execute([$c]);
        }
    }

    // ═══════════════════════════════════════════════════════
    // FULL BACKUP
    // ═══════════════════════════════════════════════════════
    public function createFullBackup(string $name, string $desc, string $admin): array
    {
        set_time_limit(0);
        $bid  = 'FULL_' . date('Y-m-d_H-i-s') . '_' . substr(md5(uniqid(mt_rand(),true)),0,8);
        $path = $this->backupRoot . 'full' . DIRECTORY_SEPARATOR . $bid;
        @mkdir($path, 0755, true);

        $lockState = json_encode($this->getLayoutLockState());
        $this->db->prepare("INSERT INTO backup_history (backup_id,backup_name,description,admin_user,backup_type,status,backup_path,retention_policy,lock_state_json) VALUES (?,?,?,?,'full','in_progress',?,'weekly',?)")
            ->execute([$bid,$name,$desc,$admin,$path,$lockState]);
        $this->audit('create_backup',null,$bid,$admin,"Started full backup: {$name}");

        try {
            // 1. Build manifest — hash every file
            $manifest = $this->buildManifest();

            // 2. Separate by segment
            $static = $dynamic = [];
            foreach ($manifest as $rel => $info) {
                if ($info['segment'] === 'static') $static[] = $rel;
                else $dynamic[] = $rel;
            }

            $compressedTotal = 0;

            // 3. Compress static assets
            if ($static) {
                $zp = $path . DIRECTORY_SEPARATOR . 'static.zip';
                $this->compressFiles($static, $zp);
                $compressedTotal += filesize($zp);
            }

            // 4. Compress dynamic files
            if ($dynamic) {
                $zp = $path . DIRECTORY_SEPARATOR . 'dynamic.zip';
                $this->compressFiles($dynamic, $zp);
                $compressedTotal += filesize($zp);
            }

            // 5. Database dump (compressed)
            $sqlPath = $path . DIRECTORY_SEPARATOR . 'database.sql';
            $dbOk = $this->dumpDatabase($sqlPath);
            if ($dbOk && file_exists($sqlPath)) {
                $this->gzCompress($sqlPath, $sqlPath . '.gz');
                @unlink($sqlPath);
                $compressedTotal += filesize($sqlPath . '.gz');
            }

            // 6. Save manifest JSON
            $mData = [
                'backup_id'    => $bid,
                'type'         => 'full',
                'created_at'   => date('c'),
                'admin'        => $admin,
                'file_count'   => count($manifest),
                'layout_locks' => $this->getLayoutLockState(),
                'database'     => $dbOk,
                'files'        => $manifest
            ];
            $mPath = $path . DIRECTORY_SEPARATOR . 'manifest.json';
            file_put_contents($mPath, json_encode($mData));

            // 7. Checksum
            $checksum = hash_file('sha256', $mPath);
            file_put_contents($path . DIRECTORY_SEPARATOR . 'checksum.sha256', $checksum);

            // 8. Store manifest in DB for dedup
            $this->storeManifestDB($bid, $manifest);

            // 9. Finalize record
            $totalOrig = array_sum(array_column($manifest, 'size'));
            $this->db->prepare("UPDATE backup_history SET status='active',file_count=?,size_bytes=?,size_compressed=?,checksum=?,includes_database=? WHERE backup_id=?")
                ->execute([count($manifest), $totalOrig, $compressedTotal, $checksum, $dbOk?1:0, $bid]);

            $this->audit('create_backup',null,$bid,$admin,
                "Full backup done: ".count($manifest)." files, ".round($totalOrig/1048576,1)."MB → ".round($compressedTotal/1048576,1)."MB");

            return ['success'=>true,'backup_id'=>$bid,'files'=>count($manifest),
                    'size_original'=>$totalOrig,'size_compressed'=>$compressedTotal];
        } catch (\Throwable $e) {
            $this->db->prepare("UPDATE backup_history SET status='failed' WHERE backup_id=?")->execute([$bid]);
            $this->audit('backup_failed',null,$bid,$admin,$e->getMessage());
            return ['success'=>false,'error'=>$e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════
    // INCREMENTAL BACKUP
    // ═══════════════════════════════════════════════════════
    public function createIncrementalBackup(string $name, string $desc, string $admin): array
    {
        set_time_limit(0);

        // Need a parent full backup
        $stmt = $this->db->prepare("SELECT backup_id FROM backup_history WHERE backup_type='full' AND status='active' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $parentId = $stmt->fetchColumn();
        if (!$parentId) return $this->createFullBackup($name.' (auto-full)', $desc, $admin);

        $bid  = 'INC_' . date('Y-m-d_H-i-s') . '_' . substr(md5(uniqid(mt_rand(),true)),0,8);
        $path = $this->backupRoot . 'incremental' . DIRECTORY_SEPARATOR . $bid;
        @mkdir($path, 0755, true);

        $lockState = json_encode($this->getLayoutLockState());
        $this->db->prepare("INSERT INTO backup_history (backup_id,backup_name,description,admin_user,backup_type,parent_backup_id,status,backup_path,retention_policy,lock_state_json) VALUES (?,?,?,?,'incremental',?,'in_progress',?,'daily',?)")
            ->execute([$bid,$name,$desc,$admin,$parentId,$path,$lockState]);
        $this->audit('create_backup',null,$bid,$admin,"Started incremental (parent: {$parentId})");

        try {
            // 1. Current manifest
            $current = $this->buildManifest();

            // 2. Previous state = parent full + all incrementals since then
            $previous = $this->loadManifestDB($parentId);
            $incStmt = $this->db->prepare("SELECT backup_id FROM backup_history WHERE parent_backup_id=? AND backup_type='incremental' AND status='active' ORDER BY created_at ASC");
            $incStmt->execute([$parentId]);
            foreach ($incStmt->fetchAll(PDO::FETCH_COLUMN) as $incId) {
                $previous = array_merge($previous, $this->loadManifestDB($incId));
            }

            // 3. Diff
            $diff = $this->diffManifests($current, $previous);
            $changed = array_merge($diff['added'], $diff['modified']);

            $compressedTotal = 0;

            // 4. Compress changed/added files only
            if ($changed) {
                $zp = $path . DIRECTORY_SEPARATOR . 'changes.zip';
                $this->compressFiles($changed, $zp);
                $compressedTotal += filesize($zp);
            }

            // 5. Deleted metadata
            if ($diff['deleted']) {
                file_put_contents($path . DIRECTORY_SEPARATOR . 'deleted.json', json_encode($diff['deleted']));
            }

            // 6. Database dump (full dump each time for safety)
            $sqlPath = $path . DIRECTORY_SEPARATOR . 'database.sql';
            $dbOk = $this->dumpDatabase($sqlPath);
            if ($dbOk && file_exists($sqlPath)) {
                $this->gzCompress($sqlPath, $sqlPath . '.gz');
                @unlink($sqlPath);
                $compressedTotal += filesize($sqlPath . '.gz');
            }

            // 7. Manifest
            $mData = [
                'backup_id'        => $bid,
                'type'             => 'incremental',
                'parent_backup_id' => $parentId,
                'created_at'       => date('c'),
                'admin'            => $admin,
                'changes'          => $diff,
                'layout_locks'     => $this->getLayoutLockState()
            ];
            $mPath = $path . DIRECTORY_SEPARATOR . 'manifest.json';
            file_put_contents($mPath, json_encode($mData));
            $checksum = hash_file('sha256', $mPath);
            file_put_contents($path . DIRECTORY_SEPARATOR . 'checksum.sha256', $checksum);

            // 8. Store changed files in DB
            $changedManifest = [];
            foreach ($changed as $f) { if (isset($current[$f])) $changedManifest[$f] = $current[$f]; }
            $this->storeManifestDB($bid, $changedManifest);

            // 9. Sizes
            $origSize = 0;
            foreach ($changed as $f) { if (isset($current[$f])) $origSize += $current[$f]['size']; }

            $this->db->prepare("UPDATE backup_history SET status='active',file_count=?,files_changed=?,files_added=?,files_deleted=?,size_bytes=?,size_compressed=?,checksum=?,includes_database=? WHERE backup_id=?")
                ->execute([count($changed),count($diff['modified']),count($diff['added']),count($diff['deleted']),$origSize,$compressedTotal,$checksum,$dbOk?1:0,$bid]);

            $this->audit('create_backup',null,$bid,$admin,
                "Incremental done: +".count($diff['added'])." ~".count($diff['modified'])." -".count($diff['deleted']));

            return ['success'=>true,'backup_id'=>$bid,'added'=>count($diff['added']),
                    'modified'=>count($diff['modified']),'deleted'=>count($diff['deleted']),
                    'compressed'=>$compressedTotal];
        } catch (\Throwable $e) {
            $this->db->prepare("UPDATE backup_history SET status='failed' WHERE backup_id=?")->execute([$bid]);
            $this->audit('backup_failed',null,$bid,$admin,$e->getMessage());
            return ['success'=>false,'error'=>$e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════
    // RESTORE
    // ═══════════════════════════════════════════════════════
    public function restoreBackup(string $backupId, string $admin): array
    {
        set_time_limit(0);
        $bk = $this->getBackup($backupId);
        if (!$bk) return ['success'=>false,'error'=>'Backup not found'];

        $this->audit('restore_start',null,$backupId,$admin,"Restoring: {$bk['backup_name']}");

        try {
            // 1. Pre-restore snapshot (atomic safety net)
            $pre = $this->createFullBackup('Pre-Restore Snapshot', "Auto before restoring {$backupId}", $admin);
            if ($pre['success']) {
                $this->db->prepare("UPDATE backup_history SET backup_type='pre_restore' WHERE backup_id=?")->execute([$pre['backup_id']]);
            }

            // 2. Perform restore based on type
            if ($bk['backup_type'] === 'full') {
                $this->doRestoreFull($bk['backup_path']);
            } elseif ($bk['backup_type'] === 'incremental') {
                $this->doRestoreChain($bk);
            } elseif ($bk['backup_type'] === 'manual') {
                $this->doRestoreLegacy($bk['backup_path']);
            } else {
                throw new \Exception("Unsupported backup type: {$bk['backup_type']}");
            }

            // 3. Restore layout lock state
            if (!empty($bk['lock_state_json'])) {
                $ls = json_decode($bk['lock_state_json'], true);
                if ($ls) $this->restoreLockState($ls);
            }

            // 4. Log restore event
            $rstId = 'RST_' . date('Y-m-d_H-i-s') . '_' . substr(md5(uniqid(mt_rand(),true)),0,8);
            $this->db->prepare("INSERT INTO backup_history (backup_id,backup_name,description,admin_user,backup_type,status,backup_path,is_restore,restored_from) VALUES (?,?,?,?,'restore','active',?,1,?)")
                ->execute([$rstId,"Restored: {$bk['backup_name']}","Restored from {$backupId}",$admin,$bk['backup_path'],$backupId]);

            $this->db->prepare("UPDATE backup_history SET status='restored' WHERE backup_id=?")->execute([$backupId]);
            $this->audit('restore_complete',null,$backupId,$admin,"Restored from: {$bk['backup_name']}");

            return ['success'=>true,'restore_id'=>$rstId,'pre_restore_id'=>$pre['backup_id']??null];
        } catch (\Throwable $e) {
            $this->audit('restore_failed',null,$backupId,$admin,$e->getMessage());
            return ['success'=>false,'error'=>$e->getMessage()];
        }
    }

    private function doRestoreFull(string $bkPath): void
    {
        // Static
        $f = $bkPath . DIRECTORY_SEPARATOR . 'static.zip';
        if (file_exists($f)) $this->extractZip($f, $this->projectRoot);

        // Dynamic
        $f = $bkPath . DIRECTORY_SEPARATOR . 'dynamic.zip';
        if (file_exists($f)) $this->extractZip($f, $this->projectRoot);

        // Database
        $gz = $bkPath . DIRECTORY_SEPARATOR . 'database.sql.gz';
        if (file_exists($gz)) {
            $tmp = $bkPath . DIRECTORY_SEPARATOR . '_restore_tmp.sql';
            $this->gzDecompress($gz, $tmp);
            $this->restoreDatabase($tmp);
            @unlink($tmp);
        }
    }

    private function doRestoreChain(array $bk): void
    {
        // 1. Restore parent full first
        $parent = $this->getBackup($bk['parent_backup_id']);
        if (!$parent) throw new \Exception("Parent backup {$bk['parent_backup_id']} missing");
        $this->doRestoreFull($parent['backup_path']);

        // 2. Apply each incremental in order up to & including target
        $stmt = $this->db->prepare("SELECT * FROM backup_history WHERE parent_backup_id=? AND backup_type='incremental' AND status IN ('active','restored') AND created_at <= ? ORDER BY created_at ASC");
        $stmt->execute([$bk['parent_backup_id'], $bk['created_at']]);

        foreach ($stmt->fetchAll() as $inc) {
            $ip = $inc['backup_path'];

            // Apply changed files
            $cz = $ip . DIRECTORY_SEPARATOR . 'changes.zip';
            if (file_exists($cz)) $this->extractZip($cz, $this->projectRoot);

            // Delete removed files
            $dj = $ip . DIRECTORY_SEPARATOR . 'deleted.json';
            if (file_exists($dj)) {
                $dels = json_decode(file_get_contents($dj), true) ?: [];
                foreach ($dels as $d) {
                    $fp = $this->projectRoot . str_replace('/', DIRECTORY_SEPARATOR, $d);
                    if (file_exists($fp)) @unlink($fp);
                }
            }

            // Database (each incremental has full DB dump)
            $gz = $ip . DIRECTORY_SEPARATOR . 'database.sql.gz';
            if (file_exists($gz)) {
                $tmp = $ip . DIRECTORY_SEPARATOR . '_restore_tmp.sql';
                $this->gzDecompress($gz, $tmp);
                $this->restoreDatabase($tmp);
                @unlink($tmp);
            }
        }
    }

    private function doRestoreLegacy(string $bkPath): void
    {
        $this->copyDirectory($bkPath, $this->projectRoot, ['.windsurf-backups', '.git']);
    }

    // ═══════════════════════════════════════════════════════
    // DATABASE DUMP / RESTORE
    // ═══════════════════════════════════════════════════════
    /**
     * Check whether shell exec functions are available (not disabled by hosting).
     */
    private function canExec(): bool
    {
        if (!function_exists('exec')) return false;
        $disabled = array_map('trim', explode(',', ini_get('disable_functions')));
        return !in_array('exec', $disabled);
    }

    private function dumpDatabase(string $out): bool
    {
        if (!$this->canExec()) {
            // Fallback: dump via PDO when exec() is disabled (Hostinger shared hosting)
            return $this->dumpDatabasePDO($out);
        }
        $h = defined('DB_HOST') ? DB_HOST : 'localhost';
        $u = defined('DB_USER') ? DB_USER : 'root';
        $p = defined('DB_PASS') ? DB_PASS : '';
        $n = defined('DB_NAME') ? DB_NAME : 'ecommerce_db';

        $cmd = "\"{$this->mysqldump}\" --host={$h} --user={$u}";
        if ($p !== '') $cmd .= " --password=\"{$p}\"";
        $cmd .= " --single-transaction --routines --triggers {$n} > \"{$out}\" 2>&1";
        exec($cmd, $output, $code);
        return $code === 0 && file_exists($out) && filesize($out) > 100;
    }

    /**
     * Pure-PHP database dump fallback when exec/mysqldump is unavailable.
     */
    private function dumpDatabasePDO(string $out): bool
    {
        try {
            $fp = fopen($out, 'w');
            if (!$fp) return false;
            $n = defined('DB_NAME') ? DB_NAME : 'ecommerce_db';
            fwrite($fp, "-- PDO dump of {$n} at " . date('Y-m-d H:i:s') . "\n");
            fwrite($fp, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $create = $this->db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
                fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($fp, $create[1] . ";\n\n");

                $rows = $this->db->query("SELECT * FROM `{$table}`");
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $vals = array_map(function($v) {
                        if ($v === null) return 'NULL';
                        return $this->db->quote($v);
                    }, array_values($row));
                    $cols = '`' . implode('`,`', array_keys($row)) . '`';
                    fwrite($fp, "INSERT INTO `{$table}` ({$cols}) VALUES (" . implode(',', $vals) . ");\n");
                }
                fwrite($fp, "\n");
            }
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fp);
            return file_exists($out) && filesize($out) > 100;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function restoreDatabase(string $sqlFile): bool
    {
        if (!$this->canExec()) {
            // Fallback: restore via PDO when exec() is disabled
            return $this->restoreDatabasePDO($sqlFile);
        }
        $h = defined('DB_HOST') ? DB_HOST : 'localhost';
        $u = defined('DB_USER') ? DB_USER : 'root';
        $p = defined('DB_PASS') ? DB_PASS : '';
        $n = defined('DB_NAME') ? DB_NAME : 'ecommerce_db';

        $cmd = "\"{$this->mysqlBin}\" --host={$h} --user={$u}";
        if ($p !== '') $cmd .= " --password=\"{$p}\"";
        $cmd .= " {$n} < \"{$sqlFile}\" 2>&1";
        exec($cmd, $output, $code);
        return $code === 0;
    }

    /**
     * Pure-PHP database restore fallback when exec/mysql CLI is unavailable.
     */
    private function restoreDatabasePDO(string $sqlFile): bool
    {
        try {
            $sql = file_get_contents($sqlFile);
            if (!$sql) return false;
            $this->db->exec("SET FOREIGN_KEY_CHECKS=0");
            $this->db->exec($sql);
            $this->db->exec("SET FOREIGN_KEY_CHECKS=1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════
    // MANIFEST & HASHING
    // ═══════════════════════════════════════════════════════
    private function buildManifest(): array
    {
        $m = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->projectRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $fi) {
            if (!$fi->isFile()) continue;
            $full = $fi->getPathname();
            $rel  = str_replace('\\', '/', substr($full, strlen($this->projectRoot)));

            // Exclude dirs
            foreach (self::EXCLUDE_DIRS as $ex) {
                if (str_starts_with($rel, $ex.'/') || str_contains($rel, '/'.$ex.'/')) continue 2;
            }
            if (in_array(basename($rel), self::EXCLUDE_FILES)) continue;

            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            $m[$rel] = [
                'hash'     => hash_file('sha256', $full),
                'size'     => $fi->getSize(),
                'modified' => $fi->getMTime(),
                'segment'  => in_array($ext, self::STATIC_EXT) ? 'static' : 'dynamic'
            ];
        }
        return $m;
    }

    private function diffManifests(array $current, array $previous): array
    {
        $added = $modified = $deleted = [];
        foreach ($current as $p => $i) {
            if (!isset($previous[$p]))                        $added[]    = $p;
            elseif ($previous[$p]['hash'] !== $i['hash'])     $modified[] = $p;
        }
        foreach ($previous as $p => $i) {
            if (!isset($current[$p])) $deleted[] = $p;
        }
        return ['added'=>$added, 'modified'=>$modified, 'deleted'=>$deleted];
    }

    private function storeManifestDB(string $bid, array $manifest): void
    {
        // Load hashes from previous full for deduplication
        $prevFull = $this->db->prepare("SELECT backup_id FROM backup_history WHERE backup_type='full' AND status='active' AND backup_id!=? ORDER BY created_at DESC LIMIT 1");
        $prevFull->execute([$bid]);
        $prevId = $prevFull->fetchColumn();

        $existing = [];
        if ($prevId) {
            $s = $this->db->prepare("SELECT file_hash,backup_id FROM backup_file_manifest WHERE backup_id=?");
            $s->execute([$prevId]);
            foreach ($s->fetchAll() as $r) $existing[$r['file_hash']] = $r['backup_id'];
        }

        $ins = $this->db->prepare("INSERT INTO backup_file_manifest (backup_id,file_path,file_hash,file_size,modified_at,storage_type,reference_backup_id,segment) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($manifest as $path => $info) {
            $type = 'stored';
            $ref  = null;
            // Dedup: same hash in previous backup → store reference only
            if (isset($existing[$info['hash']])) {
                $type = 'reference';
                $ref  = $existing[$info['hash']];
            }
            $ins->execute([$bid, $path, $info['hash'], $info['size'], $info['modified'], $type, $ref, $info['segment']]);
        }
    }

    private function loadManifestDB(string $bid): array
    {
        $s = $this->db->prepare("SELECT file_path,file_hash,file_size,modified_at,segment FROM backup_file_manifest WHERE backup_id=?");
        $s->execute([$bid]);
        $m = [];
        foreach ($s->fetchAll() as $r) {
            $m[$r['file_path']] = ['hash'=>$r['file_hash'],'size'=>(int)$r['file_size'],'modified'=>(int)$r['modified_at'],'segment'=>$r['segment']];
        }
        return $m;
    }

    // ═══════════════════════════════════════════════════════
    // COMPRESSION
    // ═══════════════════════════════════════════════════════
    private function compressFiles(array $relPaths, string $zipPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Cannot create ZIP: {$zipPath}");
        }
        foreach ($relPaths as $rel) {
            $full = $this->projectRoot . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (file_exists($full)) $zip->addFile($full, $rel);
        }
        $zip->close();
    }

    private function extractZip(string $zipPath, string $dest): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) throw new \Exception("Cannot open ZIP: {$zipPath}");
        $zip->extractTo($dest);
        $zip->close();
    }

    private function gzCompress(string $in, string $out): void
    {
        $gz = gzopen($out, 'wb9');
        $fh = fopen($in, 'rb');
        while (!feof($fh)) { gzwrite($gz, fread($fh, 65536)); }
        fclose($fh);
        gzclose($gz);
    }

    private function gzDecompress(string $gz, string $out): void
    {
        $g = gzopen($gz, 'rb');
        $f = fopen($out, 'wb');
        while (!gzeof($g)) { fwrite($f, gzread($g, 65536)); }
        fclose($f);
        gzclose($g);
    }

    // ═══════════════════════════════════════════════════════
    // CHECKSUM VERIFICATION
    // ═══════════════════════════════════════════════════════
    public function verifyChecksum(string $bid): array
    {
        $bk = $this->getBackup($bid);
        if (!$bk) return ['valid'=>false,'error'=>'Not found'];
        $mf = $bk['backup_path'] . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!file_exists($mf)) return ['valid'=>false,'error'=>'Manifest missing'];
        $cur = hash_file('sha256', $mf);
        return ['valid'=>hash_equals($bk['checksum'] ?? '', $cur), 'stored'=>$bk['checksum'], 'current'=>$cur];
    }

    // ═══════════════════════════════════════════════════════
    // AUTO-CLEANUP (RETENTION POLICY)
    // ═══════════════════════════════════════════════════════
    public function autoCleanup(): array
    {
        $result = ['archived'=>0, 'freed_mb'=>0];

        // Daily (incremental) — keep last 7
        $daily = $this->db->query("SELECT * FROM backup_history WHERE backup_type='incremental' AND status='active' ORDER BY created_at DESC")->fetchAll();
        if (count($daily) > self::RETENTION['daily']) {
            foreach (array_slice($daily, self::RETENTION['daily']) as $b) {
                $this->archiveBackup($b);
                $result['archived']++;
                $result['freed_mb'] += round($b['size_compressed']/1048576, 2);
            }
        }

        // Weekly (full) — keep last 4, but don't delete if has active children
        $weekly = $this->db->query("SELECT * FROM backup_history WHERE backup_type='full' AND status='active' ORDER BY created_at DESC")->fetchAll();
        if (count($weekly) > self::RETENTION['weekly']) {
            foreach (array_slice($weekly, self::RETENTION['weekly']) as $b) {
                $kids = $this->db->prepare("SELECT COUNT(*) FROM backup_history WHERE parent_backup_id=? AND status='active'");
                $kids->execute([$b['backup_id']]);
                if ($kids->fetchColumn() == 0) {
                    $this->archiveBackup($b);
                    $result['archived']++;
                    $result['freed_mb'] += round($b['size_compressed']/1048576, 2);
                }
            }
        }

        return $result;
    }

    private function archiveBackup(array $b): void
    {
        $this->removeDirectory($b['backup_path']);
        $this->db->prepare("UPDATE backup_history SET status='archived' WHERE backup_id=?")->execute([$b['backup_id']]);
        $this->db->prepare("DELETE FROM backup_file_manifest WHERE backup_id=?")->execute([$b['backup_id']]);
        $this->audit('auto_cleanup',null,$b['backup_id'],'System',"Archived: {$b['backup_name']}");
    }

    // ═══════════════════════════════════════════════════════
    // LAYOUT LOCK / UNLOCK
    // ═══════════════════════════════════════════════════════
    public function getLayoutLockState(): array
    {
        $s = [];
        foreach ($this->db->query("SELECT * FROM layout_locks")->fetchAll() as $l) {
            $s[$l['device_category']] = ['is_locked'=>(bool)$l['is_locked'],'locked_by'=>$l['locked_by'],'locked_at'=>$l['locked_at']];
        }
        return $s;
    }

    public function lockLayout(string $cat, string $admin): array
    {
        if (!isset(self::LAYOUT_MAP[$cat])) return ['success'=>false,'error'=>'Invalid category'];
        $n = $this->setLayoutReadOnly($cat, true);
        $this->db->prepare("UPDATE layout_locks SET is_locked=1,locked_by=?,locked_at=NOW() WHERE device_category=?")->execute([$admin,$cat]);
        $this->audit('lock_layout',$cat,null,$admin,"Locked ({$n} files)");
        return ['success'=>true,'affected'=>$n];
    }

    public function unlockLayout(string $cat, string $admin): array
    {
        if (!isset(self::LAYOUT_MAP[$cat])) return ['success'=>false,'error'=>'Invalid category'];
        $n = $this->setLayoutReadOnly($cat, false);
        $this->db->prepare("UPDATE layout_locks SET is_locked=0,unlocked_by=?,unlocked_at=NOW() WHERE device_category=?")->execute([$admin,$cat]);
        $this->audit('unlock_layout',$cat,null,$admin,"Unlocked ({$n} files)");
        return ['success'=>true,'affected'=>$n];
    }

    private function setLayoutReadOnly(string $cat, bool $lock): int
    {
        $n = 0;
        $allFiles = array_unique(array_merge(self::LAYOUT_MAP[$cat]['css'] ?? [], self::LAYOUT_MAP[$cat]['php'] ?? []));
        foreach ($allFiles as $file) {
            $path = str_ends_with($file, '.css')
                ? $this->cssDir . $file
                : $this->projectRoot . str_replace('/', DIRECTORY_SEPARATOR, $file);
            if ($this->setFileReadOnly($path, $lock)) $n++;
        }
        return $n;
    }

    private function restoreLockState(array $state): void
    {
        foreach ($state as $cat => $s) {
            if ($s['is_locked']) $this->lockLayout($cat, 'System (restore)');
            else $this->unlockLayout($cat, 'System (restore)');
        }
    }

    // ═══════════════════════════════════════════════════════
    // DELETE BACKUP
    // ═══════════════════════════════════════════════════════
    public function deleteBackup(string $bid, string $admin): array
    {
        $bk = $this->getBackup($bid);
        if (!$bk) return ['success'=>false,'error'=>'Not found'];

        // Prevent deleting full backup with active children
        if ($bk['backup_type'] === 'full') {
            $kids = $this->db->prepare("SELECT COUNT(*) FROM backup_history WHERE parent_backup_id=? AND status='active'");
            $kids->execute([$bid]);
            if ($kids->fetchColumn() > 0) return ['success'=>false,'error'=>'Has active incremental children'];
        }

        $this->removeDirectory($bk['backup_path']);
        $this->db->prepare("DELETE FROM backup_history WHERE backup_id=?")->execute([$bid]);
        $this->db->prepare("DELETE FROM backup_file_manifest WHERE backup_id=?")->execute([$bid]);
        $this->audit('delete_backup',null,$bid,$admin,"Deleted: {$bk['backup_name']}");
        return ['success'=>true];
    }

    // ═══════════════════════════════════════════════════════
    // QUERIES / STATS
    // ═══════════════════════════════════════════════════════
    public function getBackup(string $bid): ?array
    {
        $s = $this->db->prepare("SELECT * FROM backup_history WHERE backup_id=?");
        $s->execute([$bid]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getAllBackups(): array
    {
        return $this->db->query("SELECT * FROM backup_history ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAuditLog(int $limit = 50): array
    {
        return $this->db->query("SELECT * FROM layout_audit_log ORDER BY created_at DESC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDiskUsage(): array
    {
        $s = $this->db->query("SELECT
            COALESCE(SUM(CASE WHEN backup_type='full' THEN size_compressed ELSE 0 END),0) as full_size,
            COALESCE(SUM(CASE WHEN backup_type='incremental' THEN size_compressed ELSE 0 END),0) as inc_size,
            COALESCE(SUM(size_compressed),0) as total_size,
            COUNT(CASE WHEN backup_type='full' AND status='active' THEN 1 END) as full_count,
            COUNT(CASE WHEN backup_type='incremental' AND status='active' THEN 1 END) as inc_count,
            COUNT(CASE WHEN status='active' THEN 1 END) as active_count,
            COUNT(*) as total_count
            FROM backup_history WHERE status IN ('active','restored')");
        return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDeduplicationStats(): array
    {
        $total = $this->db->query("SELECT COUNT(*) FROM backup_file_manifest")->fetchColumn();
        $refs  = $this->db->query("SELECT COUNT(*) FROM backup_file_manifest WHERE storage_type='reference'")->fetchColumn();
        $savedBytes = $this->db->query("SELECT COALESCE(SUM(file_size),0) FROM backup_file_manifest WHERE storage_type='reference'")->fetchColumn();
        return ['total_entries'=>(int)$total, 'deduplicated'=>(int)$refs, 'saved_bytes'=>(int)$savedBytes];
    }

    // ═══════════════════════════════════════════════════════
    // IMPORT LEGACY MANUAL BACKUPS
    // ═══════════════════════════════════════════════════════
    public function importLegacyBackups(): int
    {
        $manualDir = $this->projectRoot . '.windsurf-backups' . DIRECTORY_SEPARATOR . 'manual' . DIRECTORY_SEPARATOR;
        if (!is_dir($manualDir)) return 0;

        $imported = $this->db->query("SELECT backup_id FROM backup_history WHERE backup_type='manual'")->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;

        foreach (scandir($manualDir) as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            $full = $manualDir . $dir;
            if (!is_dir($full)) continue;
            $mid = 'MANUAL_' . $dir;
            if (in_array($mid, $imported)) continue;

            // Parse date from dir name
            $dt = null;
            if (preg_match('/(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})-(\d{2})/', $dir, $m)) {
                $dt = $m[1] . ' ' . $m[2] . ':' . $m[3] . ':' . $m[4];
            }

            $this->db->prepare("INSERT IGNORE INTO backup_history (backup_id,backup_name,description,admin_user,backup_type,status,backup_path,created_at) VALUES (?,?,?,'System','manual','active',?,?)")
                ->execute([$mid, $dir, 'Legacy manual backup', $full, $dt]);
            $count++;
        }
        return $count;
    }

    // ═══════════════════════════════════════════════════════
    // UTILITY
    // ═══════════════════════════════════════════════════════
    private function audit(string $action, ?string $cat, ?string $bid, string $admin, string $details): void
    {
        $this->db->prepare("INSERT INTO layout_audit_log (action,device_category,backup_id,admin_user,details) VALUES (?,?,?,?,?)")
            ->execute([$action,$cat,$bid,$admin,$details]);
    }

    public function getProjectRoot(): string { return $this->projectRoot; }
    public function getCssDir(): string { return $this->cssDir; }

    /**
     * Locate a binary (mysqldump or mysql) across OS environments.
     * Windows: checks XAMPP paths. Linux: uses system PATH.
     */
    private function findBinary(string $name): string
    {
        if ($this->isWindows) {
            // XAMPP common locations
            $candidates = [
                "C:\\xampp\\mysql\\bin\\{$name}.exe",
                "D:\\xampp\\mysql\\bin\\{$name}.exe",
                "C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\{$name}.exe",
            ];
            foreach ($candidates as $c) {
                if (file_exists($c)) return $c;
            }
            // fallback: hope it's in PATH
            return $name . '.exe';
        }
        // Linux / Hostinger — check common paths
        $candidates = [
            "/usr/bin/{$name}",
            "/usr/local/bin/{$name}",
            "/usr/local/mysql/bin/{$name}",
            "/opt/cpanel/ea-mysql80/bin/{$name}",  // cPanel
        ];
        foreach ($candidates as $c) {
            if (file_exists($c)) return $c;
        }
        // fallback: use bare name (relies on PATH)
        return $name;
    }

    /**
     * Remove a directory recursively, OS-aware.
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) return;
        if ($this->canExec()) {
            if ($this->isWindows) {
                $pe = str_replace('/', '\\', $path);
                exec("rmdir /S /Q \"{$pe}\" 2>&1");
            } else {
                exec("rm -rf " . escapeshellarg($path) . " 2>&1");
            }
        } else {
            // PHP-native fallback
            $this->removeDirectoryRecursive($path);
        }
    }

    private function removeDirectoryRecursive(string $path): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) @rmdir($item->getRealPath());
            else @unlink($item->getRealPath());
        }
        @rmdir($path);
    }

    /**
     * Set file read-only or writable, OS-aware.
     */
    private function setFileReadOnly(string $filePath, bool $readOnly): bool
    {
        if (!file_exists($filePath)) return false;
        if ($this->isWindows && $this->canExec()) {
            $flag = $readOnly ? '+R' : '-R';
            exec("attrib {$flag} \"" . str_replace('/', '\\', $filePath) . "\"", $o, $rc);
            return $rc === 0;
        } else {
            // Works on Linux always; Windows fallback when exec() unavailable
            $mode = $readOnly ? 0444 : 0644;
            return @chmod($filePath, $mode);
        }
    }

    /**
     * Copy directory recursively (legacy restore), OS-aware.
     */
    private function copyDirectory(string $src, string $dst, array $excludeDirs = []): void
    {
        if ($this->canExec()) {
            if ($this->isWindows) {
                $s = str_replace('/', '\\', rtrim($src, '/\\'));
                $d = str_replace('/', '\\', rtrim($dst, '/\\'));
                $xd = implode(' ', $excludeDirs);
                exec("robocopy \"{$s}\" \"{$d}\" /E /XD {$xd} /NFL /NDL /NJH /NJS /NC /NS /NP 2>&1", $o, $rc);
                if ($rc > 7) throw new \Exception("Copy failed (robocopy code {$rc})");
            } else {
                $s = escapeshellarg(rtrim($src, '/'));
                $d = escapeshellarg(rtrim($dst, '/'));
                $excludeArgs = '';
                foreach ($excludeDirs as $ex) $excludeArgs .= " --exclude=" . escapeshellarg($ex);
                exec("rsync -a {$excludeArgs} {$s}/ {$d}/ 2>&1", $o, $rc);
                if ($rc !== 0) {
                    exec("cp -a {$s}/. {$d}/ 2>&1", $o2, $rc2);
                    if ($rc2 !== 0) throw new \Exception("Copy failed (cp code {$rc2})");
                }
            }
        } else {
            // PHP-native fallback when exec() is disabled
            $this->copyDirectoryPHP($src, $dst, $excludeDirs);
        }
    }

    private function copyDirectoryPHP(string $src, string $dst, array $excludeDirs = []): void
    {
        if (!is_dir($dst)) @mkdir($dst, 0755, true);
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            $rel = substr($item->getPathname(), strlen($src) + 1);
            // Check excludes
            $skip = false;
            foreach ($excludeDirs as $ex) {
                if (str_starts_with($rel, $ex . DIRECTORY_SEPARATOR) || str_starts_with($rel, $ex . '/')) { $skip = true; break; }
            }
            if ($skip) continue;
            $target = $dst . DIRECTORY_SEPARATOR . $rel;
            if ($item->isDir()) {
                if (!is_dir($target)) @mkdir($target, 0755, true);
            } else {
                @copy($item->getPathname(), $target);
            }
        }
    }
}
