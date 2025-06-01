<?php
// admin_users.php (continuation complète)
session_start();

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$pdo = new PDO('mysql:host=localhost;dbname=chatbot;charset=utf8', 'root', '');

// Messages de retour
$success = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_user':
                $user_id = $_POST['user_id'];
                try {
                    $pdo->beginTransaction();
                    // Supprimer les messages associés
                    $pdo->prepare("DELETE m FROM message m INNER JOIN chat_session cs ON m.session_id = cs.id WHERE cs.user_id = ?")->execute([$user_id]);
                    // Supprimer les sessions
                    $pdo->prepare("DELETE FROM chat_session WHERE user_id = ?")->execute([$user_id]);
                    // Supprimer l'utilisateur
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                    $pdo->commit();
                    $success = "Utilisateur supprimé avec succès.";
                } catch (Exception $e) {
                    $pdo->rollback();
                    $error = "Erreur lors de la suppression : " . $e->getMessage();
                }
                break;
                
            case 'toggle_status':
                $user_id = $_POST['user_id'];
                $current_status = $_POST['current_status'];
                $new_status = $current_status === 'active' ? 'inactive' : 'active';
                $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $user_id]);
                $success = "Statut mis à jour avec succès.";
                break;
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];

if ($search) {
    $where_clause = "WHERE nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR tel LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}

// Compter le total
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetch()['total'];
$total_pages = ceil($total_users / $per_page);

// Récupérer les utilisateurs
$sql = "
    SELECT u.*, 
           COUNT(DISTINCT cs.id) as session_count,
           COUNT(m.id) as message_count,
           MAX(m.timestamp) as last_message
    FROM users u
    LEFT JOIN chat_session cs ON u.id = cs.user_id
    LEFT JOIN message m ON cs.id = m.session_id AND m.sender = 'user'
    $where_clause
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - Administration</title>
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

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
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

        /* Search and Actions */
        .actions-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .search-form {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border-radius: 25px;
            padding: 0.5rem 1rem;
            flex: 1;
            max-width: 400px;
        }

        .search-form input {
            border: none;
            background: none;
            outline: none;
            padding: 0.5rem;
            font-size: 1rem;
            flex: 1;
        }

        .search-form button {
            background: none;
            border: none;
            color: #666;
            padding: 0.5rem;
            cursor: pointer;
        }

        .stats-info {
            color: #666;
            font-size: 0.9rem;
        }

        /* Users Table */
        .users-table {
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
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .action-btn {
            padding: 0.25rem 0.5rem;
            border: none;
            border-radius: 3px;
            font-size: 0.8rem;
            cursor: pointer;
            margin-right: 0.25rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-toggle {
            background: #ffc107;
            color: #212529;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .action-btn:hover {
            opacity: 0.8;
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

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 300px;
            text-align: center;
        }

        .modal-buttons {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .admin-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-form {
                max-width: none;
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
                <a href="admin_users.php" class="menu-item active">
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
                <h1>Gestion des utilisateurs</h1>
                <div class="user-info">
                    <span>Bonjour, <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                    <a href="admin_logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Search and Actions Bar -->
            <div class="actions-bar">
                <form method="GET" class="search-form">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Rechercher un utilisateur...">
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                <div class="stats-info">
                    Total: <?= number_format($total_users) ?> utilisateurs
                </div>
            </div>

            <!-- Users Table -->
            <div class="users-table">
                <?php if (empty($users)): ?>
                    <div class="no-data">
                        <i class="fas fa-users" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                        <h3>Aucun utilisateur trouvé</h3>
                        <p>Aucun utilisateur ne correspond à vos critères de recherche.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom complet</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Inscription</th>
                                <th>Sessions</th>
                                <th>Messages</th>
                                <th>Dernier message</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td title="<?= htmlspecialchars($user['nom'] . ' ' . $user['prenom']) ?>">
                                        <?= htmlspecialchars($user['nom'] . ' ' . $user['prenom']) ?>
                                    </td>
                                    <td title="<?= htmlspecialchars($user['email']) ?>">
                                        <?= htmlspecialchars($user['email']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['tel']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                    <td><?= $user['session_count'] ?></td>
                                    <td><?= $user['message_count'] ?></td>
                                    <td>
                                        <?= $user['last_message'] ? date('d/m/Y H:i', strtotime($user['last_message'])) : 'Jamais' ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $user['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                            <?= $user['status'] === 'active' ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="admin_user_detail.php?id=<?= $user['id'] ?>" class="action-btn btn-view" title="Voir les détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button onclick="toggleStatus(<?= $user['id'] ?>, '<?= $user['status'] ?>')" 
                                                class="action-btn btn-toggle" 
                                                title="<?= $user['status'] === 'active' ? 'Désactiver' : 'Activer' ?>">
                                            <i class="fas fa-<?= $user['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                        </button>
                                        <button onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['nom'] . ' ' . $user['prenom']) ?>')" 
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
                        <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                            <i class="fas fa-chevron-left"></i> Précédent
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                            Suivant <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de confirmation -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3>Confirmer l'action</h3>
            <p id="confirmMessage"></p>
            <div class="modal-buttons">
                <button onclick="closeModal()" class="action-btn" style="background: #6c757d; color: white;">Annuler</button>
                <button id="confirmBtn" class="action-btn btn-delete">Confirmer</button>
            </div>
        </div>
    </div>

    <!-- Forms cachés pour les actions -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="deleteUserId">
    </form>

    <form id="toggleForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="user_id" id="toggleUserId">
        <input type="hidden" name="current_status" id="currentStatus">
    </form>

    <script>
        function deleteUser(userId, userName) {
            document.getElementById('confirmMessage').textContent = 
                `Êtes-vous sûr de vouloir supprimer l'utilisateur "${userName}" ? Cette action est irréversible.`;
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('confirmBtn').onclick = function() {
                document.getElementById('deleteForm').submit();
            };
            document.getElementById('confirmModal').style.display = 'block';
        }

        function toggleStatus(userId, currentStatus) {
            const action = currentStatus === 'active' ? 'désactiver' : 'activer';
            document.getElementById('confirmMessage').textContent = 
                `Êtes-vous sûr de vouloir ${action} cet utilisateur ?`;
            document.getElementById('toggleUserId').value = userId;
            document.getElementById('currentStatus').value = currentStatus;
            document.getElementById('confirmBtn').onclick = function() {
                document.getElementById('toggleForm').submit();
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