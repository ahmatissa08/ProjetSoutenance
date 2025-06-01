-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : dim. 01 juin 2025 à 21:14
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `chatbot`
--

-- --------------------------------------------------------

--
-- Structure de la table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'admin',
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `admins`
--

INSERT INTO `admins` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `status`, `last_login`) VALUES
(1, 'Sougui', 'Issa', 'admin@iam.td', '$2y$10$Eazi5orpwUjGKQ2ycQZQoetwR415Bg6eab0uPL2h.LZ3RbpghWAkK', 'superadmin', 'active', '2025-06-01 18:39:05');

-- --------------------------------------------------------

--
-- Structure de la table `chat_session`
--

CREATE TABLE `chat_session` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `chat_session`
--

INSERT INTO `chat_session` (`id`, `user_id`, `created_at`) VALUES
(1, 'null', '2025-05-15 18:16:23'),
(2, '1', '2025-05-15 21:02:48'),
(3, '1', '2025-05-15 21:10:25'),
(4, 'null', '2025-05-15 21:12:23'),
(5, '1', '2025-05-15 21:14:10'),
(6, '1', '2025-05-15 21:14:53'),
(7, 'null', '2025-05-15 21:18:18'),
(8, 'null', '2025-06-01 18:02:00'),
(9, 'null', '2025-06-01 18:54:07');

-- --------------------------------------------------------

--
-- Structure de la table `message`
--

CREATE TABLE `message` (
  `id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `sender` varchar(10) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `message`
--

INSERT INTO `message` (`id`, `session_id`, `sender`, `content`, `timestamp`) VALUES
(1, 1, 'user', 'BONJOUR', '2025-05-15 18:16:23'),
(2, 1, 'bot', 'Bonjour, je suis Coumba, chatbot de l\'IAM. Comment puis-je vous aider ?', '2025-05-15 18:16:23'),
(3, 1, 'user', 'SALUT', '2025-05-15 18:16:26'),
(4, 1, 'bot', 'Bonjour, je suis Coumba, chatbot de l\'IAM. Comment puis-je vous aider ?', '2025-05-15 18:16:26'),
(5, 2, 'user', 'bonjour', '2025-05-15 21:02:49'),
(6, 2, 'bot', 'Bonjour, je suis Coumba, chatbot de l\'IAM. Comment puis-je vous aider ?', '2025-05-15 21:02:49'),
(7, 2, 'user', 'formation', '2025-05-15 21:02:59'),
(8, 2, 'bot', 'Nous offrons des formations telles que Bachelor (Informatique, Data, Droit), BBA, MBA, etc.', '2025-05-15 21:02:59'),
(9, 3, 'user', 'merci', '2025-05-15 21:10:25'),
(10, 3, 'bot', 'De rien ! Si vous avez d\'autres questions, n\'hésitez pas à demander.', '2025-05-15 21:10:25'),
(11, 3, 'user', 'presente', '2025-05-15 21:10:35'),
(12, 3, 'bot', 'Je vous écoute 😊 Vous pouvez me poser des questions sur les formations, prix, inscriptions, etc.', '2025-05-15 21:10:35'),
(13, 3, 'user', 'qui est tu', '2025-05-15 21:10:41'),
(14, 3, 'bot', 'Je vous écoute 😊 Vous pouvez me poser des questions sur les formations, prix, inscriptions, etc.', '2025-05-15 21:10:41'),
(15, 3, 'user', 'iam', '2025-05-15 21:10:47'),
(16, 3, 'bot', 'Je vous écoute 😊 Vous pouvez me poser des questions sur les formations, prix, inscriptions, etc.', '2025-05-15 21:10:47'),
(17, 4, 'user', 'bonjour', '2025-05-15 21:12:25'),
(18, 4, 'bot', 'Bonjour, je suis Coumba, chatbot de l\'IAM. Comment puis-je vous aider ?', '2025-05-15 21:12:25'),
(19, 4, 'user', 'formation', '2025-05-15 21:12:34'),
(20, 4, 'bot', 'Nous offrons des formations telles que Bachelor (Informatique, Data, Droit), BBA, MBA, etc.', '2025-05-15 21:12:34'),
(21, 5, 'user', 'quel sont les formations', '2025-05-15 21:14:10'),
(22, 5, 'bot', 'Nous offrons des formations telles que Bachelor (Informatique, Data, Droit), BBA, MBA, etc.', '2025-05-15 21:14:10'),
(23, 5, 'user', 'quel est le cout', '2025-05-15 21:14:24'),
(24, 5, 'bot', 'Je vous écoute 😊 Vous pouvez me poser des questions sur les formations, prix, inscriptions, etc.', '2025-05-15 21:14:24'),
(25, 6, 'user', 'quel est le prix', '2025-05-15 21:14:53'),
(26, 6, 'bot', 'Le coût dépend du programme choisi, mais commence à partir de 150.000 FCFA/mois.', '2025-05-15 21:14:53'),
(27, 6, 'user', 'ce quoi votre contact', '2025-05-15 21:15:45'),
(28, 6, 'bot', 'Vous pouvez nous contacter au +221 774762394 ou par email à info@iam.td.', '2025-05-15 21:15:45'),
(29, 7, 'user', 'Qui a créé ce chatbot ?', '2025-05-15 21:18:18'),
(30, 7, 'bot', 'Je vous écoute 😊 Vous pouvez me poser des questions sur les formations, prix, inscriptions, etc.', '2025-05-15 21:18:18'),
(31, 8, 'user', 'Comment ça marche ?', '2025-06-01 18:02:00'),
(32, 8, 'bot', 'Je vous écoute 😊 Vous pouvez me poser des questions sur les formations, prix, inscriptions, etc.', '2025-06-01 18:02:00'),
(33, 8, 'user', 'bonjour', '2025-06-01 18:02:07'),
(34, 8, 'bot', 'Bonjour, je suis Coumba, chatbot de l\'IAM. Comment puis-je vous aider ?', '2025-06-01 18:02:07'),
(35, 8, 'user', 'prix', '2025-06-01 18:02:19'),
(36, 8, 'bot', 'Le coût dépend du programme choisi, mais commence à partir de 150.000 FCFA/mois.', '2025-06-01 18:02:19'),
(37, 9, 'user', 'bonjour', '2025-06-01 18:54:07'),
(38, 9, 'bot', 'Bonjour, je suis Coumba, chatbot de l\'IAM. Comment puis-je vous aider ?', '2025-06-01 18:54:07'),
(39, 9, 'user', 'cv', '2025-06-01 18:54:21'),
(40, 9, 'bot', 'Je vous écoute 😊 Vous pouvez me poser des questions sur les formations, prix, inscriptions, etc.', '2025-06-01 18:54:21');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `tel` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `prenom`, `tel`, `email`, `code`, `last_login`, `created_at`) VALUES
(1, 'AHMAT', 'Issa Sougui', '7746363', 'issasougui08@gmail.com', '$2y$10$a0ChuWiIw3YExizS3dRV7OllyQFy6qg/rqBpfklNDotoGl8OGhA1W', NULL, '2025-06-01 18:52:04');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `chat_session`
--
ALTER TABLE `chat_session`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `chat_session`
--
ALTER TABLE `chat_session`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `message`
--
ALTER TABLE `message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `message`
--
ALTER TABLE `message`
  ADD CONSTRAINT `message_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `chat_session` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
