<?php
/**
 * YJH - Your Jobs Here
 * Secure Automated Installation Script v2.0
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Безопасность: не показываем ошибки

// Защита от прямого доступа к конфигам
define('YJH_INSTALL', true);

// Check if already installed
if (file_exists(__DIR__ . '/config/installed.lock')) {
    header('Location: login.php');
    exit('System already installed. Delete config/installed.lock to reinstall.');
}

// Создаём необходимые директории
$directories = ['config', 'data', 'logs', 'uploads', 'backups'];
foreach ($directories as $dir) {
    if (!is_dir(__DIR__ . '/' . $dir)) {
        mkdir(__DIR__ . '/' . $dir, 0755, true);
    }
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Step 1: Check requirements
if ($step == 1) {
    $requirements = [
        'PHP >= 8.0' => version_compare(PHP_VERSION, '8.0', '>='),
        'PHP >= 8.2 (рекомендуется)' => version_compare(PHP_VERSION, '8.2', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO SQLite Extension' => extension_loaded('pdo_sqlite'),
        'SQLite3 Extension' => extension_loaded('sqlite3'),
        'OpenSSL Extension' => extension_loaded('openssl'),
        'JSON Extension' => extension_loaded('json'),
        'config/ directory writable' => is_writable(__DIR__ . '/config'),
        'data/ directory writable' => is_writable(__DIR__ . '/data'),
        'logs/ directory writable' => is_writable(__DIR__ . '/logs'),
        'uploads/ directory writable' => is_writable(__DIR__ . '/uploads'),
    ];
    
    $allPassed = !in_array(false, $requirements);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allPassed) {
        $_SESSION['install_step1'] = true;
        header('Location: install.php?step=2');
        exit;
    }
}

// Step 2: Database setup
if ($step == 2) {
    // Проверяем, что прошли шаг 1
    if (empty($_SESSION['install_step1'])) {
        header('Location: install.php?step=1');
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $adminEmail = trim($_POST['email'] ?? '');
        $adminPassword = $_POST['password'] ?? '';
        $adminPasswordConfirm = $_POST['password_confirm'] ?? '';
        $adminName = trim($_POST['name'] ?? 'Administrator');
        $siteName = trim($_POST['site_name'] ?? 'YJH - Your Jobs Here');
        
        // Валидация
        if (empty($adminEmail) || empty($adminPassword)) {
            $error = 'Email and password are required';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } elseif (strlen($adminPassword) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif ($adminPassword !== $adminPasswordConfirm) {
            $error = 'Passwords do not match';
        } elseif (!preg_match('/[A-Z]/', $adminPassword) || !preg_match('/[a-z]/', $adminPassword) || !preg_match('/[0-9]/', $adminPassword)) {
            $error = 'Password must contain uppercase, lowercase and numbers';
        } else {
            try {
                // Initialize database
                $dbFile = __DIR__ . '/data/database.sqlite';
                
                // Если файл БД существует, создаём бэкап
                if (file_exists($dbFile)) {
                    $backupFile = __DIR__ . '/backups/database_backup_' . date('Y-m-d_H-i-s') . '.sqlite';
                    copy($dbFile, $backupFile);
                }
                
                $pdo = new PDO("sqlite:$dbFile");
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Create tables if schema exists
                $schemaFile = __DIR__ . '/database/schema.sql';
                if (file_exists($schemaFile)) {
                    $schema = file_get_contents($schemaFile);
                    $pdo->exec($schema);
                } else {
                    // Minimal schema if file doesn't exist
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS users (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            name TEXT NOT NULL,
                            email TEXT UNIQUE NOT NULL,
                            password TEXT NOT NULL,
                            role TEXT DEFAULT 'user',
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                            last_login DATETIME,
                            twofa_secret TEXT,
                            twofa_enabled INTEGER DEFAULT 0
                        );
                        
                        CREATE TABLE IF NOT EXISTS projects (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            name TEXT NOT NULL,
                            description TEXT,
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                            owner_id INTEGER,
                            FOREIGN KEY (owner_id) REFERENCES users(id)
                        );
                        
                        CREATE TABLE IF NOT EXISTS bugs (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            title TEXT NOT NULL,
                            description TEXT,
                            status TEXT DEFAULT 'open',
                            priority TEXT DEFAULT 'medium',
                            severity TEXT DEFAULT 'normal',
                            project_id INTEGER,
                            created_by INTEGER,
                            assigned_to INTEGER,
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                            updated_at DATETIME,
                            FOREIGN KEY (project_id) REFERENCES projects(id),
                            FOREIGN KEY (created_by) REFERENCES users(id),
                            FOREIGN KEY (assigned_to) REFERENCES users(id)
                        );
                        
                        CREATE TABLE IF NOT EXISTS settings (
                            key TEXT PRIMARY KEY,
                            value TEXT,
                            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                        );
                    ");
                }
                
                // Create admin user
                $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, created_at) 
                    VALUES (?, ?, ?, 'admin', datetime('now'))
                ");
                $stmt->execute([$adminName, $adminEmail, $hashedPassword]);
                
                // Save site settings
                $stmt = $pdo->prepare("
                    INSERT OR REPLACE INTO settings (key, value, updated_at) 
                    VALUES ('site_name', ?, datetime('now'))
                ");
                $stmt->execute([$siteName]);
                
                $stmt = $pdo->prepare("
                    INSERT OR REPLACE INTO settings (key, value, updated_at) 
                    VALUES ('installed_version', '1.0.0', datetime('now'))
                ");
                $stmt->execute();
                
                // Create config file (безопасно, вне document root)
                $configContent = "<?php
// YJH Configuration - Auto-generated on " . date('Y-m-d H:i:s') . "
return [
    'database' => [
        'driver' => 'sqlite',
        'path' => '" . addslashes($dbFile) . "'
    ],
    'site_name' => '" . addslashes($siteName) . "',
    'debug' => false,
    'version' => '1.0.0',
    'installed' => true,
    'security' => [
        'session_lifetime' => 7200,
        'rate_limit' => 100,
        'max_login_attempts' => 5
    ]
];";
                file_put_contents(__DIR__ . '/config/app.php', $configContent);
                
                // Create .htaccess for security
                $htaccess = "# YJH Security Configuration
<FilesMatch \"^\\.\">
    Order allow,deny
    Deny from all
</FilesMatch>

<FilesMatch \"^(config|data|logs|backups)\">
    Order allow,deny
    Deny from all
</FilesMatch>

Options -Indexes
DirectoryIndex index.php

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options \"nosniff\"
    Header set X-Frame-Options \"DENY\"
    Header set X-XSS-Protection \"1; mode=block\"
</IfModule>";
                file_put_contents(__DIR__ . '/.htaccess', $htaccess);
                
                // Create lock file
                file_put_contents(__DIR__ . '/config/installed.lock', date('Y-m-d H:i:s') . "\nInstalled by: $adminEmail");
                
                // Clear session
                session_destroy();
                
                $success = 'Installation completed successfully! Redirecting to login...';
                
                // Redirect to login after 2 seconds
                header('refresh:2;url=login.php');
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
                // Логируем ошибку
                error_log($e->getMessage(), 3, __DIR__ . '/logs/install_error.log');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YJH Installation - Your Jobs Here</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 650px;
            width: 100%;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .content { padding: 30px; }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
        }
        .step { color: #999; font-weight: bold; }
        .step.active { color: #667eea; }
        .step.completed { color: #27ae60; }
        .requirement {
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            background: #f8f9fa;
        }
        .requirement.pass { background: #d4edda; color: #155724; }
        .requirement.fail { background: #f8d7da; color: #721c24; }
        .requirement.warning { background: #fff3cd; color: #856404; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        label .required { color: #e74c3c; }
        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }
        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        button:hover { transform: translateY(-2px); opacity: 0.9; }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 YJH Installation</h1>
            <p>Your Jobs Here - Professional QA Platform</p>
        </div>
        
        <div class="content">
            <div class="step-indicator">
                <span class="step <?php echo $step == 1 ? 'active' : ''; ?> <?php echo isset($_SESSION['install_step1']) ? 'completed' : ''; ?>">
                    1. Requirements
                </span>
                <span class="step <?php echo $step == 2 ? 'active' : ''; ?>">
                    2. Database Setup
                </span>
            </div>
            
            <?php if ($error): ?>
                <div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <h3>📋 System Requirements Check</h3>
                <p style="color: #666; margin-bottom: 20px;">Please ensure all requirements are met before continuing.</p>
                
                <?php foreach ($requirements as $req => $pass): ?>
                    <div class="requirement <?php echo $pass ? 'pass' : 'fail'; ?>">
                        <span><?php echo $req; ?></span>
                        <span><?php echo $pass ? '✓ Passed' : '✗ Failed'; ?></span>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($allPassed): ?>
                    <div class="info">
                        💡 <strong>Next step:</strong> You'll create an admin account and configure the database.
                    </div>
                    <form method="POST">
                        <button type="submit">Continue to Setup →</button>
                    </form>
                <?php else: ?>
                    <div class="error">
                        ❌ Please fix the failed requirements above before continuing.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($step == 2): ?>
                <h3>🔧 Create Admin Account</h3>
                <p style="color: #666; margin-bottom: 20px;">Set up your administrator account for YJH.</p>
                
                <form method="POST" onsubmit="return validateForm()">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="name" value="Administrator" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" required placeholder="admin@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Site Name</label>
                        <input type="text" name="site_name" value="YJH - Your Jobs Here">
                    </div>
                    
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" id="password" required onkeyup="checkStrength()">
                        <div class="password-strength" id="strength"></div>
                        <small style="color: #666;">Minimum 8 characters, with uppercase, lowercase and numbers</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <input type="password" name="password_confirm" id="password_confirm" required>
                    </div>
                    
                    <hr>
                    
                    <button type="submit">🚀 Install YJH</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function checkStrength() {
            var password = document.getElementById('password').value;
            var strength = document.getElementById('strength');
            
            var strengthValue = 0;
            if (password.length >= 8) strengthValue++;
            if (password.match(/[A-Z]/)) strengthValue++;
            if (password.match(/[a-z]/)) strengthValue++;
            if (password.match(/[0-9]/)) strengthValue++;
            if (password.match(/[^a-zA-Z0-9]/)) strengthValue++;
            
            if (strengthValue <= 2) {
                strength.innerHTML = 'Weak password';
                strength.className = 'password-strength strength-weak';
            } else if (strengthValue <= 4) {
                strength.innerHTML = 'Medium password';
                strength.className = 'password-strength strength-medium';
            } else {
                strength.innerHTML = 'Strong password!';
                strength.className = 'password-strength strength-strong';
            }
            
            if (password.length === 0) {
                strength.innerHTML = '';
            }
        }
        
        function validateForm() {
            var password = document.getElementById('password').value;
            var confirm = document.getElementById('password_confirm').value;
            
            if (password !== confirm) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>