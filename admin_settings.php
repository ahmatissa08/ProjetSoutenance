<?php
// admin_settings.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$pdo = new PDO('mysql:host=localhost;dbname=chatbot;charset=utf8', 'root', '');

$message = '';
$error = '';

// Traitement des formulaires
if ($_POST) {
    try {
        if (isset($_POST['update_profile'])) {
            // Mise à jour du profil admin
            $nom = trim($_POST['nom']);
            $prenom = trim($_POST['prenom']);
            $email = trim($_POST['email']);
            
            if (empty($nom) || empty($prenom) || empty($email)) {
                $error = "Tous les champs sont obligatoires.";
            } else {
                // Vérifier si l'email existe déjà (pour un autre admin)
                $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
                $stmt->execute([$email, $_SESSION['admin_id']]);
                
                if ($stmt->fetch()) {
                    $error = "Cet email est déjà utilisé par un autre administrateur.";
                } else {
                    $stmt = $pdo->prepare("UPDATE admins SET nom = ?, prenom = ?, email = ? WHERE id = ?");
                    $stmt->execute([$nom, $prenom, $email, $_SESSION['admin_id']]);
                    $_SESSION['admin_name'] = $prenom . ' ' . $nom;
                    $message = "Profil mis à jour avec succès.";
                }
            }
        }
        
        if (isset($_POST['change_password'])) {
            // Changement de mot de passe
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = "Tous les champs de mot de passe sont obligatoires.";
            } elseif ($new_password !== $confirm_password) {
                $error = "Les nouveaux mots de passe ne correspondent pas.";
            } elseif (strlen($new_password) < 6) {
                $error = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
            } else {
                // Vérifier l'ancien mot de passe
                $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                $admin = $stmt->fetch();
                
                if (!password_verify($current_password, $admin['password'])) {
                    $error = "Mot de passe actuel incorrect.";
                } else {
                    // Mettre à jour le mot de passe
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['admin_id']]);
                    $message = "Mot de passe changé avec succès.";
                }
            }
        }
        
        if (isset($_POST['add_admin'])) {
            // Ajouter un nouvel administrateur
            $nom = trim($_POST['new_nom']);
            $prenom = trim($_POST['new_prenom']);
            $email = trim($_POST['new_email']);
            $password = $_POST['new_password'];
            $role = $_POST['new_role'];
            
            if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
                $error = "Tous les champs sont obligatoires pour créer un administrateur.";
            } elseif (strlen($password) < 6) {
                $error = "Le mot de passe doit contenir au moins 6 caractères.";
            } else {
                // Vérifier si l'email existe déjà
                $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $error = "Cet email est déjà utilisé.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO admins (nom, prenom, email, password, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$nom, $prenom, $email, $hashed_password, $role]);
                    $message = "Nouvel administrateur créé avec succès.";
                }
            }
        }
        
        if (isset($_POST['delete_admin'])) {
            // Supprimer un administrateur
            $admin_id = $_POST['admin_id'];
            
            if ($admin_id == $_SESSION['admin_id']) {
                $error = "Vous ne pouvez pas supprimer votre propre compte.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                $stmt->execute([$admin_id]);
                $message = "Administrateur supprimé avec succès.";
            }
        }
        
        if (isset($_POST['clear_old_messages'])) {
            // Nettoyer les anciens messages (plus de 30 jours)
            $stmt = $pdo->prepare("DELETE FROM message WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $deleted_count = $stmt->rowCount();
            $message = "$deleted_count anciens messages supprimés.";
        }
        
        if (isset($_POST['clear_old_sessions'])) {
            // Nettoyer les anciennes sessions (plus de 30 jours)
            $stmt = $pdo->prepare("DELETE FROM chat_session WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $deleted_count = $stmt->rowCount();
            $message = "$deleted_count anciennes sessions supprimées.";
        }
        
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer les informations de l'admin actuel
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$current_admin = $stmt->fetch();

// Récupérer tous les administrateurs
$stmt = $pdo->query("SELECT * FROM admins ORDER BY nom, prenom");
$all_admins = $stmt->fetchAll();

// Statistiques de la base de données
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM chat_session) as total_sessions,
        (SELECT COUNT(*) FROM message) as total_messages,
        (SELECT COUNT(*) FROM message WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)) as old_messages,
        (SELECT COUNT(*) FROM chat_session WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)) as old_sessions
");
$db_stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
        }

        .admin-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: #2c3e50;
            color: white;
            padding: 2rem 0;
        }

        .sidebar-header {
            padding: 0 2rem 2rem;
            border-bottom: 1px solid #34495e;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .sidebar-header p {
            color: #bdc3c7;
            font-size: 0.9rem;
        }

        .sidebar-menu {
            padding: 2rem 0;
        }

        .menu-item {
            display: block;
            padding: 1rem 2rem;
            color: #ecf0f1;
            text-decoration: none;
            transition: background 0.3s;
        }

        .menu-item:hover, .menu-item.active {
            background: #34495e;
        }

        .menu-item i {
            margin-right: 1rem;
            width: 20px;
        }

        /* Main Content */
        .main-content {
            padding: 2rem;
        }

        .header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
        }

        /* Settings Content */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .settings-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .settings-card h3 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #2ecc71;
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .admin-list {
            list-style: none;
        }

        .admin-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #ecf0f1;
        }

        .admin-item:last-child {
            border-bottom: none;
        }

        .admin-info h4 {
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .admin-info p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        @media (max-width: 768px) {
            .admin-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-robot"></i> Admin IAM</h2>
                <p>Panneau d'administration</p>
            </div>
            
            <nav class="sidebar-menu">
                <a href="admin_dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i> Tableau de bord
                </a>
                <a href="admin_users.php" class="menu-item">
                    <i class="fas fa-users"></i> Utilisateurs
                </a>
                <a href="admin_conversations.php" class="menu-item">
                    <i class="fas fa-comments"></i> Conversations
                </a>
                <a href="admin_analytics.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i> Statistiques
                </a>
                <a href="admin_settings.php" class="menu-item active">
                    <i class="fas fa-cog"></i> Paramètres
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Paramètres</h1>
                <div class="user-info">
                    <span>Bonjour, <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                    <a href="admin_logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- Profil Admin -->
                <div class="settings-card">
                    <h3><i class="fas fa-user-edit"></i> Mon Profil</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="nom">Nom</label>
                            <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($current_admin['nom']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom</label>
                            <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($current_admin['prenom']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($current_admin['email']) ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Mettre à jour
                        </button>
                    </form>
                </div>

                <!-- Changement de mot de passe -->
                <div class="settings-card">
                    <h3><i class="fas fa-key"></i> Changer le mot de passe</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="current_password">Mot de passe actuel</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Nouveau mot de passe</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmer le mot de passe</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-warning">
                            <i class="fas fa-lock"></i> Changer le mot de passe
                        </button>
                    </form>
                </div>

                <!-- Gestion des administrateurs -->
                <?php if ($current_admin['role'] === 'superadmin'): ?>
                <div class="settings-card">
                    <h3><i class="fas fa-user-plus"></i> Ajouter un administrateur</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="new_nom">Nom</label>
                            <input type="text" id="new_nom" name="new_nom" required>
                        </div>
                        <div class="form-group">
                            <label for="new_prenom">Prénom</label>
                            <input type="text" id="new_prenom" name="new_prenom" required>
                        </div>
                        <div class="form-group">
                            <label for="new_email">Email</label>
                            <input type="email" id="new_email" name="new_email" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Mot de passe</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_role">Rôle</label>
                            <select id="new_role" name="new_role" required>
                                <option value="admin">Administrateur</option>
                                <option value="superadmin">Super Administrateur</option>
                            </select>
                        </div>
                        <button type="submit" name="add_admin" class="btn btn-success">
                            <i class="fas fa-plus"></i> Ajouter
                        </button>
                    </form>
                </div>

                <!-- Liste des administrateurs -->
                <div class="settings-card">
                    <h3><i class="fas fa-users-cog"></i> Administrateurs</h3>
                    <ul class="admin-list">
                        <?php foreach ($all_admins as $admin): ?>
                        <li class="admin-item">
                            <div class="admin-info">
                                <h4><?= htmlspecialchars($admin['nom'] . ' ' . $admin['prenom']) ?></h4>
                                <p><?= htmlspecialchars($admin['email']) ?> - <?= htmlspecialchars($admin['role']) ?></p>
                            </div>
                            <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet administrateur ?')">
                                <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                                <button type="submit" name="delete_admin" class="btn btn-danger" style="padding: 0.5rem;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Maintenance de la base de données -->
                <div class="settings-card">
                    <h3><i class="fas fa-database"></i> Maintenance</h3>
                    <div class="stat-item">
                        <span>Utilisateurs totaux</span>
                        <strong><?= number_format($db_stats['total_users']) ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>Sessions totales</span>
                        <strong><?= number_format($db_stats['total_sessions']) ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>Messages totaux</span>
                        <strong><?= number_format($db_stats['total_messages']) ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>Anciens messages (>30j)</span>
                        <strong><?= number_format($db_stats['old_messages']) ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>Anciennes sessions (>30j)</span>
                        <strong><?= number_format($db_stats['old_sessions']) ?></strong>
                    </div>
                    
                    <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer tous les messages de plus de 30 jours ?')">
                            <button type="submit" name="clear_old_messages" class="btn btn-warning">
                                <i class="fas fa-broom"></i> Nettoyer les messages
                            </button>
                        </form>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer toutes les sessions de plus de 30 jours ?')">
                            <button type="submit" name="clear_old_sessions" class="btn btn-warning">
                                <i class="fas fa-broom"></i> Nettoyer les sessions
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Informations système -->
                <div class="settings-card">
                    <h3><i class="fas fa-info-circle"></i> Informations système</h3>
                    <div class="stat-item">
                        <span>Version PHP</span>
                        <strong><?= PHP_VERSION ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>Serveur Web</span>
                        <strong><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Non disponible' ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>Base de données</span>
                        <strong>MySQL/MariaDB</strong>
                    </div>
                    <div class="stat-item">
                        <span>Dernière connexion</span>
                        <strong><?= $current_admin['last_login'] ? date('d/m/Y H:i', strtotime($current_admin['last_login'])) : 'Jamais' ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Confirmation avant suppression
        function confirmDelete(message) {
            return confirm(message || 'Êtes-vous sûr de vouloir effectuer cette action ?');
        }

        // Auto-hide des messages d'alerte après 5 secondes
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>