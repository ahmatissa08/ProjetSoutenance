<?php
// chat.php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$username = $isLoggedIn ? $_SESSION['username'] : "invité";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot IAM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/stylechat.css">
</head>
<body>
    <div class="app-container">
        <div class="sidebar sidebar-hidden" id="sidebar">
            <div class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-chevron-right" id="toggleIcon"></i>
            </div>
            <div class="sidebar-header">
                <i class="fas fa-history"></i> Historique des conversations
            </div>
            <div class="sidebar-content" id="historyList">
                <div class="no-history">
                    <i class="fas fa-info-circle"></i> Aucun historique disponible
                </div>
            </div>
        </div>

        <div class="container">
            <header>
                <div class="user-info">
                    <div class="app-title">
                        <i class="fas fa-robot"></i> Chatbot IAM
                    </div>
                    <div class="user-profile">
                        <div class="avatar">
                            <?= strtoupper(substr($username, 0, 1)) ?>
                        </div>
                        <span class="user-name"><?= htmlspecialchars($username) ?></span>
                    </div>
                </div>
                <button class="dark-mode-toggle" id="darkModeToggle">
                    <i class="fas fa-moon"></i>
                </button>
            </header>

            <div class="chat-container">
                <div class="chat-box" id="chatBox">
                    <div class="message bot">
                        <div class="message-info">
                            <i class="fas fa-robot"></i> Coumba
                        </div>
                        <div class="message-content">
                            Bonjour <?= htmlspecialchars($username) ?> ! Comment puis-je vous aider aujourd'hui ?
                        </div>
                    </div>
                    
                    <div class="suggestion-chips">
                        <div class="suggestion-chip" onclick="useChip('Quelles sont tes fonctionnalités ?')">
                            <i class="fas fa-list-ul"></i> Fonctionnalités
                        </div>
                        <div class="suggestion-chip" onclick="useChip('Comment ça marche ?')">
                            <i class="fas fa-question-circle"></i> Comment ça marche ?
                        </div>
                        <div class="suggestion-chip" onclick="useChip('Qui a créé ce chatbot ?')">
                            <i class="fas fa-user-cog"></i> À propos
                        </div>
                    </div>
                    
                    <div class="typing-indicator" id="typingIndicator">
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                    </div>
                </div>

                <div class="input-area">
                    <input type="text" id="userInput" placeholder="Entrez votre message..." autocomplete="off">
                    <button onclick="sendMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-buttons">
        <?php if (!$isLoggedIn): ?>
            <button class="auth-button" onclick="window.location='register.php'">
                <i class="fas fa-user-plus"></i> S'inscrire
            </button>
            <button class="auth-button" onclick="window.location='login.php'">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        <?php else: ?>
            <button class="auth-button" onclick="window.location='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Se déconnecter
            </button>
        <?php endif; ?>
    </div>

    <script>
        let sessionId = null;
        const typingIndicator = document.getElementById("typingIndicator");
        const chatBox = document.getElementById("chatBox");
        const userInput = document.getElementById("userInput");
        const sidebar = document.getElementById("sidebar");
        const toggleIcon = document.getElementById("toggleIcon");
        const historyList = document.getElementById("historyList");
        const darkModeToggle = document.getElementById("darkModeToggle");
        
        // Variables pour la gestion des conversations
        let conversations = [];
        let currentConversationId = null;
        let isDarkMode = false;

        // Initialisation du mode sombre
        function initDarkMode() {
            isDarkMode = localStorage.getItem('darkMode') === 'true';
            if (isDarkMode) {
                document.body.classList.add('dark-mode');
                darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                document.body.classList.remove('dark-mode');
                darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            }
        }

        // Gestionnaire pour le bouton de mode sombre
        darkModeToggle.addEventListener('click', function() {
            isDarkMode = !isDarkMode;
            localStorage.setItem('darkMode', isDarkMode);
            initDarkMode();
        });

        // Fonction pour basculer l'affichage de la barre latérale
        function toggleSidebar() {
            sidebar.classList.toggle("sidebar-hidden");
            if (sidebar.classList.contains("sidebar-hidden")) {
                toggleIcon.className = "fas fa-chevron-right";
            } else {
                toggleIcon.className = "fas fa-chevron-left";
                loadConversations(); // Recharger les conversations à chaque ouverture
            }
        }

        // Fonction pour afficher le message dans la boîte de chat
        function appendMessage(who, text, isTyping = false, timestamp = new Date().toISOString()) {
            if (isTyping) {
                typingIndicator.style.display = "flex";
                chatBox.scrollTop = chatBox.scrollHeight;
                return;
            }

            // Cacher l'indicateur de frappe
            typingIndicator.style.display = "none";

            // Créer un nouveau message
            const messageDiv = document.createElement("div");
            messageDiv.className = `message ${who === 'Vous' ? 'user' : 'bot'}`;

            const messageInfo = document.createElement("div");
            messageInfo.className = "message-info";
            
            if (who === 'Vous') {
                messageInfo.innerHTML = `${who} <i class="fas fa-user"></i>`;
            } else {
                messageInfo.innerHTML = `<i class="fas fa-robot"></i> ${who}`;
            }
            
            const messageContent = document.createElement("div");
            messageContent.className = "message-content";
            messageContent.textContent = text;

            messageDiv.appendChild(messageInfo);
            messageDiv.appendChild(messageContent);
            
            // Insérer avant l'indicateur de frappe
            chatBox.insertBefore(messageDiv, typingIndicator);
            chatBox.scrollTop = chatBox.scrollHeight;
            
            // Sauvegarder le message dans l'historique
            if (!isTyping && currentConversationId) {
                saveMessageToHistory(who, text, timestamp);
            }
        }

        // Fonction pour envoyer un message
        function sendMessage() {
            const message = userInput.value.trim();
            if (!message) return;
            
            // S'il n'y a pas de conversation actuelle, en créer une nouvelle
            if (!currentConversationId) {
                startNewConversation();
            }

            appendMessage("Vous", message);
            appendMessage("", "", true); // Afficher l'indicateur de frappe

            fetch("http://localhost:5000/chat", {
                method: "POST",
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    user_id: <?= json_encode($_SESSION['user_id'] ?? "null") ?>,
                    message: message,
                    session_id: sessionId
                })
            })
            .then(res => res.json())
            .then(data => {
                sessionId = data.session_id;
                
                // Simuler un délai de réponse plus naturel
                setTimeout(() => {
                    appendMessage("Coumba", data.response);
                }, 500 + Math.random() * 1000);
            })
            .catch(error => {
                console.error("Erreur:", error);
                setTimeout(() => {
                    appendMessage("Coumba", "Désolé, une erreur s'est produite. Veuillez réessayer plus tard.");
                }, 500);
            });

            userInput.value = '';
        }

        // Fonction pour utiliser les suggestions
        function useChip(text) {
            userInput.value = text;
            sendMessage();
        }

        // Événement pour envoyer le message avec la touche Entrée
        userInput.addEventListener("keypress", function(event) {
            if (event.key === "Enter") {
                sendMessage();
            }
        });

        // Fonction pour générer une animation de message de bienvenue
        function animateWelcomeMessage() {
            const welcomeText = "Bonjour " + "<?= htmlspecialchars($username) ?>" + " ! Comment puis-je vous aider aujourd'hui ?";
            const welcomeDiv = document.querySelector(".message.bot .message-content");
            welcomeDiv.textContent = "";
            
            let i = 0;
            const typeInterval = setInterval(() => {
                if (i < welcomeText.length) {
                    welcomeDiv.textContent += welcomeText.charAt(i);
                    i++;
                } else {
                    clearInterval(typeInterval);
                }
            }, 30);
        }

        // Fonction pour démarrer une nouvelle conversation
        function startNewConversation() {
            const newId = Date.now().toString();
            currentConversationId = newId;
            
            // Ajouter la nouvelle conversation à la liste
            conversations.unshift({
                id: newId,
                title: "Nouvelle conversation",
                date: new Date().toISOString(),
                messages: [
                    {
                        who: 'bot',
                        content: "Bonjour " + "<?= htmlspecialchars($username) ?>" + " ! Comment puis-je vous aider aujourd'hui ?",
                        timestamp: new Date().toISOString()
                    }
                ]
            });
            
            // Sauvegarder les conversations
            saveConversations();
            
            // Mettre à jour l'affichage de l'historique
            renderConversations();
        }

        // Fonction pour sauvegarder un message dans l'historique
        function saveMessageToHistory(who, content, timestamp) {
            const conversation = conversations.find(c => c.id === currentConversationId);
            if (conversation) {
                conversation.messages.push({
                    who: who === 'Vous' ? 'user' : 'bot',
                    content,
                    timestamp
                });
                
                // Mettre à jour le titre de la conversation avec le premier message utilisateur
                if (who === 'Vous' && conversation.title === "Nouvelle conversation" && conversation.messages.filter(m => m.who === 'user').length === 1) {
                    conversation.title = content.length > 25 ? content.substring(0, 25) + "..." : content;
                }
                
                // Sauvegarder les conversations
                saveConversations();
                
                // Mettre à jour l'affichage de l'historique
                renderConversations();
            }
        }

        // Fonction pour sauvegarder les conversations dans localStorage
        function saveConversations() {
            if (<?= $isLoggedIn ? 'true' : 'false' ?>) {
                localStorage.setItem('conversations_' + <?= json_encode($_SESSION['user_id'] ?? "") ?>, JSON.stringify(conversations));
            }
        }

        // Fonction pour charger les conversations depuis localStorage
        function loadConversations() {
            if (<?= $isLoggedIn ? 'true' : 'false' ?>) {
                const stored = localStorage.getItem('conversations_' + <?= json_encode($_SESSION['user_id'] ?? "") ?>);
                if (stored) {
                    conversations = JSON.parse(stored);
                    renderConversations();
                }
            }
        }

        // Fonction pour afficher les conversations dans la barre latérale
        function renderConversations() {
            historyList.innerHTML = '';
            
            if (conversations.length === 0) {
                historyList.innerHTML = `
                    <div class="no-history">
                        <i class="fas fa-info-circle"></i> Aucun historique disponible
                    </div>
                `;
                return;
            }
            
            conversations.forEach(conv => {
                const dateObj = new Date(conv.date);
                const formattedDate = dateObj.toLocaleDateString('fr-FR', { 
                    day: '2-digit', 
                    month: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                }).replace(',', ' à');
                
                const historyItem = document.createElement('div');
                historyItem.className = `history-item ${conv.id === currentConversationId ? 'active' : ''}`;
                historyItem.dataset.id = conv.id;
                
                historyItem.innerHTML = `
                    <div class="history-text">${escape(conv.title)}</div>
                    <div class="history-date">${formattedDate}</div>
                    <div class="history-actions">
                        <button class="history-action" onclick="deleteConversation('${conv.id}', event)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
                
                historyItem.addEventListener('click', function(e) {
                    if (!e.target.closest('.history-action')) {
                        loadConversation(conv.id);
                    }
                });
                
                historyList.appendChild(historyItem);
            });
        }

        // Fonction pour éviter les problèmes d'injection HTML
        function escape(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Fonction pour charger une conversation spécifique
        function loadConversation(id) {
            const conversation = conversations.find(c => c.id === id);
            if (!conversation) return;
            
            currentConversationId = id;
            
            // Effacer le chat actuel
            chatBox.innerHTML = '';
            
            // Ajouter les messages de la conversation
            conversation.messages.forEach(msg => {
                const who = msg.who === 'user' ? 'Vous' : 'Coumba';
                appendMessage(who, msg.content, false, msg.timestamp);
            });
            
            // Ajouter l'indicateur de frappe à la fin
            const typingIndicator = document.createElement('div');
            typingIndicator.className = 'typing-indicator';
            typingIndicator.id = 'typingIndicator';
            typingIndicator.innerHTML = `
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
            `;
            typingIndicator.style.display = 'none';
            chatBox.appendChild(typingIndicator);
            
            // Ajouter à nouveau les suggestions si c'est une nouvelle conversation
            if (conversation.messages.length <= 1) {
                const suggestionChips = document.createElement('div');
                suggestionChips.className = 'suggestion-chips';
                suggestionChips.innerHTML = `
                    <div class="suggestion-chip" onclick="useChip('Quelles sont tes fonctionnalités ?')">
                        <i class="fas fa-list-ul"></i> Fonctionnalités
                    </div>
                    <div class="suggestion-chip" onclick="useChip('Comment ça marche ?')">
                        <i class="fas fa-question-circle"></i> Comment ça marche ?
                    </div>
                    <div class="suggestion-chip" onclick="useChip('Qui a créé ce chatbot ?')">
                        <i class="fas fa-user-cog"></i> À propos
                    </div>
                `;
                chatBox.insertBefore(suggestionChips, typingIndicator);
            }
            
            // Mettre à jour l'affichage de l'historique
            renderConversations();
        }

        // Fonction pour supprimer une conversation
        function deleteConversation(id, event) {
            event.stopPropagation();
            
            // Confirmation avant suppression
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette conversation ?')) {
                return;
            }
            
            // Supprimer la conversation
            conversations = conversations.filter(c => c.id !== id);
            
            // Si c'était la conversation active, en créer une nouvelle
            if (id === currentConversationId) {
                chatBox.innerHTML = '';
                const botMessage = document.createElement('div');
                botMessage.className = 'message bot';
                botMessage.innerHTML = `
                    <div class="message-info">
                        <i class="fas fa-robot"></i> Coumba
                    </div>
                    <div class="message-content">
                        Bonjour <?= htmlspecialchars($username) ?> ! Comment puis-je vous aider aujourd'hui ?
                    </div>
                `;
                chatBox.appendChild(botMessage);
                
                // Ajouter les suggestions
                const suggestionChips = document.createElement('div');
                suggestionChips.className = 'suggestion-chips';
                suggestionChips.innerHTML = `
                    <div class="suggestion-chip" onclick="useChip('Quelles sont tes fonctionnalités ?')">
                        <i class="fas fa-list-ul"></i> Fonctionnalités
                    </div>
                    <div class="suggestion-chip" onclick="useChip('Comment ça marche ?')">
                        <i class="fas fa-question-circle"></i> Comment ça marche ?
                    </div>
                    <div class="suggestion-chip" onclick="useChip('Qui a créé ce chatbot ?')">
                        <i class="fas fa-user-cog"></i> À propos
                    </div>
                `;
                chatBox.appendChild(suggestionChips);
                
                // Ajouter l'indicateur de frappe
                const typingIndicator = document.createElement('div');
                typingIndicator.className = 'typing-indicator';
                typingIndicator.id = 'typingIndicator';
                typingIndicator.innerHTML = `
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                `;
                typingIndicator.style.display = 'none';
                chatBox.appendChild(typingIndicator);
                
                currentConversationId = null;
            }
            
            // Sauvegarder les conversations
            saveConversations();
            
            // Mettre à jour l'affichage de l'historique
            renderConversations();
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Initialiser le mode sombre
            initDarkMode();
            
            // Charger les conversations
            loadConversations();
            
            // Animer le message de bienvenue
            animateWelcomeMessage();
            
            // Mettre le focus sur le champ de saisie
            userInput.focus();
        });
    </script>
</body>
</html>