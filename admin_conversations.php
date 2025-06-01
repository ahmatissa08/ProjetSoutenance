<?php
// admin_conversations.php
session_start();

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$pdo = new PDO('mysql:host=localhost;dbname=chatbot;charset=utf8', 'root', '');

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Filtres
$user_filter = isset($_GET['user']) ? trim($_GET['user']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Construction de la requête
$where_conditions = [];
$params = [];

if ($user_filter) {
    $where_conditions[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $search_param = "%$user_filter%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($date_filter) {
    $where_conditions[] = "DATE(cs.created_at) = ?";
    $params[] = $date_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Compter le total
$count_sql = "
    SELECT COUNT(DISTINCT cs.id) as total 
    FROM chat_session cs 
    LEFT JOIN users u ON cs.user_id = u.id 
    $where_clause
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_sessions = $stmt->fetch()['total'];
$total_pages = ceil($total_sessions / $per_page);

// Récupérer les conversations
$sql = "
    SELECT 
        cs.id as session_id,
        cs.created_at,
        u.nom,
        u.prenom,
        u.email,
        COUNT(m.id) as message_count,
        MAX(m.timestamp) as last_message,
        MIN(CASE WHEN m.sender = 'user' THEN m.content END) as first_user_message
    FROM chat_session cs
    LEFT JOIN users u ON cs.user_id = u.id
    LEFT JOIN message m ON cs.id = m.session_id
    $where_clause
    GROUP BY cs.id
    ORDER BY cs.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conversations = $stmt->fetchAll();

// Statistiques rapides
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM chat_session");
$stats['total_sessions'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as today FROM chat_session WHERE DATE(created_at) = CURDATE()");
$stats['sessions_today'] = $stmt->fetch()['today'];

$stmt = $pdo->query("SELECT AVG(message_count) as avg_messages FROM (SELECT COUNT(*) as message_count FROM message GROUP BY session_id) as session_stats");
$stats['avg_messages'] = round($stmt->fetch()['avg_messages'], 1);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des conversations - Administration</title>
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
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #3498db;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        /* Filters */
        .filters-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 500;
            color: #2c3e50;
        }

        .filter-group input, .filter-group select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .filter-btn {
            background: #3498db;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .clear-btn {
            background: #95a5a6;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
        }

        /* Conversations Table */
        .conversations-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 1px solid #dee2e6;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: top;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .conversation-preview {
            max-width: 300px;
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .user-info-cell {
            min-width: 200px;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .user-email {
            color: #666;
            font-size: 0.85rem;
        }

        .message-stats {
            text-align: center;
            font-weight: 600;
            color: #3498db;
        }

        .date-info {
            font-size: 0.9rem;
            color: #666;
        }

        .action-btn {
            background: #3498db;
            color: white;
            padding: 0.25rem 0.75rem;
            border: none;
            border-radius: 3px;
            text-decoration: none;
            font-size: 0.8rem;
            margin-right: 0.25rem;
        }

        .action-btn:hover {
            opacity: 0.8;
        }

        .btn-view {
            background: #17a2b8;
        }

        .btn-delete {
            background: #dc3545;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 2rem 0;
            gap: 0.5rem;
        }

        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            text-decoration: none;
            color: #495057;
            border-radius: 5px;
        }

        .pagination a:hover {
            background: #e9ecef;
        }

        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .admin-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                justify-content: space-between;
            }
            
            table {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 0.5rem;
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
                <a href="admin_conversations.php" class="menu-item active">
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
                <h1>Gestion des conversations</h1>
                <div class="user-info">
                    <span>Bonjour, <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                    <a href="admin_logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-row">
                <div class="stat-card">
                    <i class="fas fa-comments"></i>
                    <div class="stat-number"><?= number_format($stats['total_sessions']) ?></div>
                    <div class="stat-label">Conversations totales</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-calendar-day"></i>
                    <div class="stat-number"><?= number_format($stats['sessions_today']) ?></div>
                    <div class="stat-label">Aujourd'hui</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-chart-line"></i>
                    <div class="stat-number"><?= $stats['avg_messages'] ?></div>
                    <div class="stat-label">Messages/conversation</div>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="filters-bar">
                <form method="GET" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <div class="filter-group">
                        <label for="user">Utilisateur:</label>
                        <input type="text" id="user" name="user" value="<?= htmlspecialchars($user_filter) ?>" 
                               placeholder="Nom, email...">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date">Date:</label>
                        <input type="date" id="date" name="date" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                    
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    
                    <a href="admin_conversations.php" class="clear-btn">
                        <i class="fas fa-times"></i> Effacer
                    </a>
                </form>
                
                <div style="margin-left: auto; color: #666; font-size: 0.9rem;">
                    <?= number_format($total_sessions) ?> conversation(s) trouvée(s)
                </div>
            </div>

            <!-- Conversations Table -->
            <div class="conversations-table">
                <?php if (empty($conversations)): ?>
                    <div class="no-data">
                        <i class="fas fa-comments" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                        <h3>Aucune conversation trouvée</h3>
                        <p>Aucune conversation ne correspond à vos critères de recherche.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Utilisateur</th>
                                <th>Premier message</th>
                                <th>Messages</th>
                                <th>Créée le</th>
                                <th>Dernier message</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conversations as $conv): ?>
                                <tr>
                                    <td><?= $conv['session_id'] ?></td>
                                    <td class="user-info-cell">
                                        <div class="user-name">
                                            <?= htmlspecialchars($conv['nom'] . ' ' . $conv['prenom']) ?>
                                        </div>
                                        <div class="user-email">
                                            <?= htmlspecialchars($conv['email']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="conversation-preview">
                                            <?php if ($conv['first_user_message']): ?>
                                                <?= htmlspecialchars(substr($conv['first_user_message'], 0, 100)) ?>
                                                <?= strlen($conv['first_user_message']) > 100 ? '...' : '' ?>
                                            <?php else: ?>
                                                <em style="color: #999;">Aucun message utilisateur</em>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="message-stats">
                                        <?= $conv['message_count'] ?>
                                    </td>
                                    <td class="date-info">
                                        <?= date('d/m/Y H:i', strtotime($conv['created_at'])) ?>
                                    </td>
                                    <td class="date-info">
                                        <?= $conv['last_message'] ? date('d/m/Y H:i', strtotime($conv['last_message'])) : 'Aucun' ?>
                                    </td>
                                    <td>
                                        <a href="admin_conversation_detail.php?id=<?= $conv['session_id'] ?>" 
                                           class="action-btn btn-view" title="Voir la conversation">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button onclick="deleteConversation(<?= $conv['session_id'] ?>)" 
                                                class="action-btn btn-delete" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $user_filter ? '&user=' . urlencode($user_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>">
                            <i class="fas fa-chevron-left"></i> Précédent
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?><?= $user_filter ? '&user=' . urlencode($user_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $user_filter ? '&user=' . urlencode($user_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>">
                            Suivant <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de confirmation -->
    <div id="confirmModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 15% auto; padding: 2rem; border-radius: 10px; width: 300px; text-align: center;">
            <h3>Confirmer la suppression</h3>
            <p>Êtes-vous sûr de vouloir supprimer cette conversation ? Cette action est irréversible.</p>
            <div style="margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center;">
                <button onclick="closeModal()" style="background: #6c757d; color: white; padding: 0.5rem 1rem; border: none; border-radius: 5px; cursor: pointer;">Annuler</button>
                <button id="confirmBtn" style="background: #dc3545; color: white; padding: 0.5rem 1rem; border: none; border-radius: 5px; cursor: pointer;">Supprimer</button>
            </div>
        </div>
    </div>

    <!-- Form caché pour suppression -->
    <form id="deleteForm" method="POST" action="admin_conversation_delete.php" style="display: none;">
        <input type="hidden" name="session_id" id="deleteSessionId">
    </form>

    <script>
        function deleteConversation(sessionId) {
            document.getElementById('deleteSessionId').value = sessionId;
            document.getElementById('confirmBtn').onclick = function() {
                document.getElementById('deleteForm').submit();
            };
            document.getElementById('confirmModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        // Fermer le modal en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>