<?php
// admin_dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$pdo = new PDO('mysql:host=localhost;dbname=chatbot;charset=utf8', 'root', '');

// Récupérer les statistiques
$stats = [];

// Nombre total d'utilisateurs
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch()['total'];

// Nombre d'utilisateurs actifs (connectés dans les 7 derniers jours)
$stmt = $pdo->query("SELECT COUNT(*) as active FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['active_users'] = $stmt->fetch()['active'];

// Nombre total de sessions de chat
$stmt = $pdo->query("SELECT COUNT(*) as total FROM chat_session");
$stats['total_sessions'] = $stmt->fetch()['total'];

// Nombre total de messages
$stmt = $pdo->query("SELECT COUNT(*) as total FROM message");
$stats['total_messages'] = $stmt->fetch()['total'];

// Messages aujourd'hui
$stmt = $pdo->query("SELECT COUNT(*) as today FROM message WHERE DATE(timestamp) = CURDATE()");
$stats['messages_today'] = $stmt->fetch()['today'];

// Nouveaux utilisateurs cette semaine
$stmt = $pdo->query("SELECT COUNT(*) as new_users FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['new_users_week'] = $stmt->fetch()['new_users'];

// Messages récents
$stmt = $pdo->query("
    SELECT m.*, u.nom, u.prenom, u.email 
    FROM message m 
    LEFT JOIN chat_session cs ON m.session_id = cs.id 
    LEFT JOIN users u ON cs.user_id = u.id 
    ORDER BY m.timestamp DESC 
    LIMIT 10
");
$recent_messages = $stmt->fetchAll();

// Utilisateurs les plus actifs
$stmt = $pdo->query("
    SELECT u.nom, u.prenom, u.email, COUNT(m.id) as message_count
    FROM users u
    LEFT JOIN chat_session cs ON u.id = cs.user_id
    LEFT JOIN message m ON cs.id = m.session_id
    WHERE m.sender = 'user'
    GROUP BY u.id
    ORDER BY message_count DESC
    LIMIT 5
");
$active_users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Administration</title>
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .stat-card.users i { color: #3498db; }
        .stat-card.sessions i { color: #2ecc71; }
        .stat-card.messages i { color: #e67e22; }
        .stat-card.active i { color: #9b59b6; }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 1rem;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .content-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .content-card h3 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
        }

        .message-item, .user-item {
            padding: 1rem;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .message-item:last-child, .user-item:last-child {
            border-bottom: none;
        }

        .message-content {
            flex: 1;
        }

        .message-user {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .message-text {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .message-time {
            color: #95a5a6;
            font-size: 0.8rem;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .user-stats {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .admin-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .content-grid {
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
                <a href="admin_dashboard.php" class="menu-item active">
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
                <a href="admin_settings.php" class="menu-item">
                    <i class="fas fa-cog"></i> Paramètres
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Tableau de bord</h1>
                <div class="user-info">
                    <span>Bonjour, <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                    <a href="admin_logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card users">
                    <i class="fas fa-users"></i>
                    <div class="stat-number"><?= number_format($stats['total_users']) ?></div>
                    <div class="stat-label">Utilisateurs totaux</div>
                </div>
                
                <div class="stat-card active">
                    <i class="fas fa-user-check"></i>
                    <div class="stat-number"><?= number_format($stats['active_users']) ?></div>
                    <div class="stat-label">Utilisateurs actifs</div>
                </div>
                
                <div class="stat-card sessions">
                    <i class="fas fa-comment-dots"></i>
                    <div class="stat-number"><?= number_format($stats['total_sessions']) ?></div>
                    <div class="stat-label">Sessions de chat</div>
                </div>
                
                <div class="stat-card messages">
                    <i class="fas fa-envelope"></i>
                    <div class="stat-number"><?= number_format($stats['total_messages']) ?></div>
                    <div class="stat-label">Messages totaux</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Messages -->
                <div class="content-card">
                    <h3><i class="fas fa-clock"></i> Messages récents</h3>
                    <?php if (empty($recent_messages)): ?>
                        <p style="color: #7f8c8d; text-align: center; padding: 2rem;">
                            Aucun message récent
                        </p>
                    <?php else: ?>
                        <?php foreach ($recent_messages as $message): ?>
                            <div class="message-item">
                                <div class="message-content">
                                    <div class="message-user">
                                        <?= htmlspecialchars($message['nom'] . ' ' . $message['prenom']) ?>
                                    </div>
                                    <div class="message-text">
                                        <?= htmlspecialchars(substr($message['content'], 0, 50)) ?>
                                        <?= strlen($message['content']) > 50 ? '...' : '' ?>
                                    </div>
                                    <div class="message-time">
                                        <?= date('d/m/Y H:i', strtotime($message['timestamp'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Active Users -->
                <div class="content-card">
                    <h3><i class="fas fa-star"></i> Utilisateurs les plus actifs</h3>
                    <?php if (empty($active_users)): ?>
                        <p style="color: #7f8c8d; text-align: center; padding: 2rem;">
                            Aucune donnée disponible
                        </p>
                    <?php else: ?>
                        <?php foreach ($active_users as $user): ?>
                            <div class="user-item">
                                <div>
                                    <div class="user-name">
                                        <?= htmlspecialchars($user['nom'] . ' ' . $user['prenom']) ?>
                                    </div>
                                    <div class="user-stats">
                                        <?= $user['message_count'] ?> messages envoyés
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>