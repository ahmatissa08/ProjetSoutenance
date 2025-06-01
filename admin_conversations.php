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

        .user-info