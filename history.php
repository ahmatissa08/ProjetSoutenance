<?php
// history.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("http://localhost:5000/history?user_id=$user_id"), true);
?>

<h2>Historique de vos discussions</h2>

<?php foreach ($data as $session): ?>
    <h3>Session #<?= $session['session_id'] ?></h3>
    <ul>
    <?php foreach ($session['messages'] as $msg): ?>
        <li><strong><?= $msg['sender'] ?>:</strong> <?= htmlspecialchars($msg['content']) ?> (<?= $msg['timestamp'] ?>)</li>
    <?php endforeach; ?>
    </ul>
<?php endforeach; ?>
