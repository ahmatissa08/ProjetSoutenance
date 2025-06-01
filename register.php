<?php
// register.php
session_start();
$pdo = new PDO('mysql:host=localhost;dbname=chatbot;charset=utf8', 'root', '');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $tel = $_POST['tel'];
    $email = $_POST['email'];
    $code = password_hash($_POST['code'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        $error = "Email déjà utilisé.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, tel, email, code) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nom, $prenom, $tel, $email, $code]);
        header("Location: login.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription</title>
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
    <div class="container">
        <h2>Créer un compte</h2>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" id="registrationForm">
            <div class="form-group">
                <label>Nom</label>
                <input name="nom" required>
            </div>

            <div class="form-group">
                <label>Prénom</label>
                <input name="prenom" required>
            </div>

            <div class="form-group">
                <label>Téléphone</label>
                <input name="tel" type="tel" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="code" id="password" required>
                <div class="password-strength" id="passwordStrength"></div>
            </div>

            <div class="form-group">
                <label>Confirmer le mot de passe</label>
                <input type="password" id="confirmPassword" required>
                <small id="passwordError" style="color: #dc3545; display: none;">Les mots de passe ne correspondent pas</small>
            </div>

            <button type="submit">S’inscrire</button>
        </form>
    </div>

    <script>
        const form = document.getElementById('registrationForm');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirmPassword');
        const passwordError = document.getElementById('passwordError');
        const strengthBar = document.getElementById('passwordStrength');

        password.addEventListener('input', updatePasswordStrength);
        confirmPassword.addEventListener('input', validatePasswordMatch);
        
        form.addEventListener('submit', function(e) {
            if (!validatePasswordMatch() || !validateForm()) {
                e.preventDefault();
            }
        });

        function validateForm() {
            const inputs = form.querySelectorAll('input[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = '#dc3545';
                } else {
                    input.style.borderColor = '#dadce0';
                }
            });

            return isValid;
        }

        function validatePasswordMatch() {
            if (password.value !== confirmPassword.value) {
                passwordError.style.display = 'block';
                confirmPassword.style.borderColor = '#dc3545';
                return false;
            }
            passwordError.style.display = 'none';
            confirmPassword.style.borderColor = '#dadce0';
            return true;
        }

        function updatePasswordStrength() {
            const strength = calculateStrength(password.value);
            strengthBar.style.setProperty('--width', strength + '%');
            strengthBar.style.backgroundColor = getStrengthColor(strength);
        }

        function calculateStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength += 40;
            if (/[A-Z]/.test(password)) strength += 20;
            if (/[0-9]/.test(password)) strength += 20;
            if (/[^A-Za-z0-9]/.test(password)) strength += 20;
            return Math.min(strength, 100);
        }

        function getStrengthColor(strength) {
            if (strength < 40) return '#dc3545';
            if (strength < 70) return '#ffc107';
            return '#28a745';
        }
    </script>
</body>
</html>