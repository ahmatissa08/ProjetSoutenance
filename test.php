<?php
$input = 'admin123';
$hash = '$2y$10$Eazi5orpwUjGKQ2ycQZQoetwR415Bg6eab0uPL2h.LZ3RbpghWAkK'; // Mets ici ton hash complet

if (password_verify($input, $hash)) {
    echo "✅ Mot de passe correct.";
} else {
    echo "❌ Mot de passe incorrect.";
}
