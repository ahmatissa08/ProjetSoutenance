<?php
// login.php
session_start();
$pdo = new PDO('mysql:host=localhost;dbname=chatbot;charset=utf8', 'root', '');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $code = $_POST['code'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($code, $user['code'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['prenom'] . ' ' . $user['nom'];
        header("Location: chat.php");
        exit;
    } else {
        $error = "Identifiants incorrects.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="container">
        <h2>Connexion au Chatbot</h2>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label>Email institutionnel</label>
                <input type="email" name="email" required autofocus>
            </div>

            <div class="form-group">
                <label>Code étudiant</label>
                <input type="password" name="code" id="password" required>
                <span class="password-toggle" id="togglePassword">👁️</span>
            </div>

            <button type="submit">Se connecter</button>

            <div class="links">
                <a href="forgot-password.php">Mot de passe oublié ?</a>
                <br>
                <a href="register.php">Créer un compte</a>
            </div>
        </form>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const form = document.getElementById('loginForm');

        // Toggle password visibility
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.textContent = type === 'password' ? '👁️' : '👁️🗨️';
        });

        // Real-time validation
        form.addEventListener('submit', function(e) {
            const inputs = form.querySelectorAll('input[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = '#dc3545';
                } else {
                    input.style.borderColor = '#dadce0';
                }

                // Email validation
                if (input.type === 'email' && !validateEmail(input.value)) {
                    isValid = false;
                    input.style.borderColor = '#dc3545';
                }
            });

            if (!isValid) {
                e.preventDefault();
                if (!document.querySelector('.error')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error';
                    errorDiv.textContent = 'Veuillez remplir tous les champs correctement';
                    form.prepend(errorDiv);
                }
            }
        });

        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Auto-hide error after 5 seconds
        setTimeout(() => {
            const errorDiv = document.querySelector('.error');
            if (errorDiv) errorDiv.style.display = 'none';
        }, 5000);
    </script>
</body>
</html>