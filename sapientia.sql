-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mer. 11 nov. 2026 à 12:34
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
-- Base de données : `sapientia`
--

-- --------------------------------------------------------

--
-- Structure de la table `chapitre`
--

CREATE TABLE `chapitre` (
  `id_chapitre` int(11) NOT NULL,
  `id_cours` int(11) DEFAULT NULL,
  `titre` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordre` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `chapitre`
--

INSERT INTO `chapitre` (`id_chapitre`, `id_cours`, `titre`, `description`, `ordre`) VALUES
(1, 1, 'Limites', 'Calcul des limites et formes indéterminées', 1),
(2, 1, 'Continuité', 'Fonctions continues', 2),
(3, 1, 'Dérivabilité', 'Dérivées et interprétation graphique', 3),
(4, 1, 'Étude de fonctions', 'Variations et asymptotes', 4),
(5, 1, 'Intégration', 'Primitives et intégrales définies', 5),
(6, 1, 'Suites numériques', 'Suites arithmétiques et géométriques', 6),
(7, 1, 'Probabilités', 'Variables aléatoires discrètes', 7),
(8, 1, 'Statistiques', 'Moyenne, variance, écart-type', 8),
(9, 1, 'Nombres complexes', 'Formes algébrique et trigonométrique', 9),
(10, 1, 'Géométrie de l’espace', 'Vecteurs et droites', 10),
(11, 2, 'Cinématique', 'Mouvement du point matériel', 1),
(12, 2, 'Dynamique', 'Lois de Newton', 2),
(13, 2, 'Travail et énergie', 'Énergie cinétique et potentielle', 3),
(14, 2, 'Électricité CC', 'Loi d’Ohm et circuits', 4),
(15, 2, 'Condensateurs', 'Charge et décharge', 5),
(16, 2, 'Ondes mécaniques', 'Propagation des ondes', 6),
(25, 4, 'grammaire', 'grammaire', 1),
(26, 3, 'writing', 'writing', 1);

-- --------------------------------------------------------

--
-- Structure de la table `chatbot_interaction`
--

CREATE TABLE `chatbot_interaction` (
  `id_interaction` int(11) NOT NULL,
  `id_utilisateur` int(11) DEFAULT NULL,
  `question` text DEFAULT NULL,
  `reponse` text DEFAULT NULL,
  `date_interaction` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commentaire`
--

CREATE TABLE `commentaire` (
  `id_commentaire` int(11) NOT NULL,
  `id_utilisateur` int(11) DEFAULT NULL,
  `id_ressource` int(11) DEFAULT NULL,
  `contenu` text DEFAULT NULL,
  `date_commentaire` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `commentaire`
--

INSERT INTO `commentaire` (`id_commentaire`, `id_utilisateur`, `id_ressource`, `contenu`, `date_commentaire`) VALUES
(8, 18, 23, 'thanks', '2025-12-14 19:37:06'),
(9, 18, 16, 'thanks', '2025-12-14 19:38:17'),
(10, 18, 16, 'dazdada', '2025-12-16 13:11:01');

-- --------------------------------------------------------

--
-- Structure de la table `cours`
--

CREATE TABLE `cours` (
  `id_cours` int(11) NOT NULL,
  `titre` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `niveau` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `cours`
--

INSERT INTO `cours` (`id_cours`, `titre`, `description`, `niveau`) VALUES
(1, 'Mathématiques', 'Programme officiel Bac Math Tunisie', 'Bac Math'),
(2, 'Physique', 'Physique Bac Math Tunisie', 'Bac Math'),
(3, 'Anglais', 'Anglais scientifique Bac', 'Bac'),
(4, 'Français', 'Français Bac Tunisie', 'Bac');

-- --------------------------------------------------------

--
-- Structure de la table `exercice`
--

CREATE TABLE `exercice` (
  `id_exercice` int(11) NOT NULL,
  `id_chapitre` int(11) DEFAULT NULL,
  `enonce` text DEFAULT NULL,
  `difficulte` varchar(20) DEFAULT NULL,
  `correction` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `exercice`
--

INSERT INTO `exercice` (`id_exercice`, `id_chapitre`, `enonce`, `difficulte`, `correction`) VALUES
(21, 1, 'Calculer la limite de 3x² − 5x quand x→+∞', 'facile', 'La limite est +∞'),
(22, 1, 'Calculer la limite de (2x+1)/(x−1)', 'moyen', 'La limite est 2'),
(23, 2, 'Montrer que f est continue sur R', 'facile', 'Somme de fonctions continues'),
(24, 3, 'Calculer la dérivée de f(x)=x³−2x+1', 'facile', 'f’(x)=3x²−2'),
(25, 4, 'Dresser le tableau de variations de f', 'moyen', 'Étude du signe de f’'),
(26, 5, 'Calculer ∫(2x+3)dx', 'facile', 'x²+3x+C'),
(27, 5, 'Calculer ∫(x²−1)dx', 'moyen', 'x³/3−x+C'),
(28, 6, 'Déterminer le terme général d’une suite arithmétique', 'facile', 'Un = U0 + nr'),
(29, 7, 'Calculer l’espérance d’une variable aléatoire', 'difficile', 'E(X)=Σxi·pi'),
(30, 8, 'Calculer la variance d’une série statistique', 'moyen', 'V=E(X²)−[E(X)]²'),
(31, 9, 'Écrire un complexe sous forme trigonométrique', 'difficile', 'z=r(cosθ+i sinθ)'),
(32, 10, 'Déterminer une équation de droite dans l’espace', 'moyen', 'Vecteur directeur'),
(33, 11, 'Déterminer la vitesse d’un mobile', 'facile', 'v = dx/dt'),
(34, 12, 'Appliquer la deuxième loi de Newton', 'moyen', 'ΣF = m·a'),
(35, 13, 'Calculer l’énergie cinétique d’un corps', 'facile', 'Ec = 1/2 mv²'),
(36, 14, 'Appliquer la loi d’Ohm', 'facile', 'U = R·I'),
(37, 15, 'Calculer la charge d’un condensateur', 'moyen', 'Q = C·U'),
(38, 16, 'Déterminer la célérité d’une onde', 'difficile', 'v = λ·f'),
(39, 25, 'verbre etre au present (avec il)', NULL, 'est'),
(40, 26, 'contraire de old', NULL, 'new');

-- --------------------------------------------------------

--
-- Structure de la table `exercice_progression`
--

CREATE TABLE `exercice_progression` (
  `id` int(11) NOT NULL,
  `id_utilisateur` int(11) DEFAULT NULL,
  `id_exercice` int(11) DEFAULT NULL,
  `statut` enum('non_commence','en_cours','termine') DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `date_derniere_modif` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `exercice_progression`
--

INSERT INTO `exercice_progression` (`id`, `id_utilisateur`, `id_exercice`, `statut`, `score`, `date_derniere_modif`) VALUES
(33, 18, 22, 'termine', NULL, '2025-12-14 19:26:27'),
(34, 18, 21, 'en_cours', NULL, '2025-12-14 19:26:31'),
(35, 18, 40, 'termine', NULL, '2025-12-14 19:36:51'),
(36, 18, 39, 'termine', NULL, '2025-12-16 13:09:43');

-- --------------------------------------------------------

--
-- Structure de la table `logs`
--

CREATE TABLE `logs` (
  `id_log` int(11) NOT NULL,
  `id_utilisateur` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `date_action` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_adresse` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `logs`
--

INSERT INTO `logs` (`id_log`, `id_utilisateur`, `action`, `date_action`, `ip_adresse`) VALUES
(17, 2, 'Connexion réussie', '2025-12-01 22:27:27', NULL),
(18, 2, 'Déconnexion manuelle réussie', '2025-12-01 22:27:30', NULL),
(20, 2, 'Connexion réussie', '2025-12-01 22:29:50', NULL),
(21, 2, 'Connexion réussie', '2025-12-01 22:32:22', NULL),
(24, 2, 'Connexion réussie', '2025-12-01 22:36:34', NULL),
(25, 2, 'Déconnexion manuelle réussie', '2025-12-01 22:36:36', NULL),
(29, 2, 'Connexion réussie', '2025-12-01 22:40:32', NULL),
(45, 2, 'Connexion réussie', '2025-12-14 19:09:22', NULL),
(48, 18, 'Connexion réussie', '2025-12-14 19:25:20', NULL),
(49, 18, 'Ajout d\'un commentaire sur Ressource ID: 23', '2025-12-14 19:37:06', NULL),
(50, 18, 'Ajout d\'un commentaire sur Ressource ID: 16', '2025-12-14 19:38:17', NULL),
(51, 2, 'Connexion réussie', '2025-12-15 10:56:28', NULL),
(52, 18, 'Connexion réussie', '2025-12-15 22:11:49', NULL),
(53, 2, 'Connexion réussie', '2025-12-15 22:34:21', NULL),
(54, 2, 'Connexion réussie', '2025-12-16 12:51:19', NULL),
(55, 18, 'Connexion réussie', '2025-12-16 13:08:30', NULL),
(56, 18, 'Ajout d\'un commentaire sur Ressource ID: 16', '2025-12-16 13:11:01', NULL),
(57, 18, 'Déconnexion manuelle réussie', '2025-12-16 13:11:49', NULL),
(58, 2, 'Connexion réussie', '2026-11-10 18:56:52', NULL),
(59, 2, 'Connexion réussie', '2026-11-11 11:25:32', NULL),
(60, 2, 'Déconnexion manuelle réussie', '2026-11-11 11:25:34', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `parametresite`
--

CREATE TABLE `parametresite` (
  `id_parametre` int(11) NOT NULL,
  `cle` varchar(100) DEFAULT NULL,
  `valeur` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `progression`
--

CREATE TABLE `progression` (
  `id_progression` int(11) NOT NULL,
  `id_utilisateur` int(11) DEFAULT NULL,
  `id_cours` int(11) NOT NULL,
  `pourcentage_global` int(11) DEFAULT 0,
  `dernier_acces` datetime DEFAULT NULL,
  `est_termine` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `progression`
--

INSERT INTO `progression` (`id_progression`, `id_utilisateur`, `id_cours`, `pourcentage_global`, `dernier_acces`, `est_termine`) VALUES
(19, 18, 4, 0, '2025-12-16 14:09:43', 1),
(20, 18, 1, 0, '2025-12-14 20:26:31', 0),
(21, 18, 2, 0, '2025-12-14 20:30:23', 0),
(22, 18, 3, 0, '2025-12-14 20:36:51', 1);

-- --------------------------------------------------------

--
-- Structure de la table `ressource`
--

CREATE TABLE `ressource` (
  `id_ressource` int(11) NOT NULL,
  `id_chapitre` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `titre` varchar(100) DEFAULT NULL,
  `fichier_url` varchar(255) DEFAULT NULL,
  `date_ajout` timestamp NOT NULL DEFAULT current_timestamp(),
  `contenu` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `ressource`
--

INSERT INTO `ressource` (`id_ressource`, `id_chapitre`, `type`, `titre`, `fichier_url`, `date_ajout`, `contenu`) VALUES
(16, 1, 'pdf', 'Cours Limites – Bac Math', 'uploads/limites.pdf', '2025-12-14 19:21:24', NULL),
(17, 3, 'pdf', 'Cours Dérivées – Bac Math', 'uploads/derivees.pdf', '2025-12-14 19:21:24', NULL),
(18, 5, 'pdf', 'Cours Intégration – Bac Math', 'uploads/integration.pdf', '2025-12-14 19:21:24', NULL),
(19, 7, 'pdf', 'Cours Probabilités – Bac Math', 'uploads/probabilites.pdf', '2025-12-14 19:21:24', NULL),
(20, 12, 'pdf', 'Lois de Newton – Résumé', 'uploads/newton.pdf', '2025-12-14 19:21:24', NULL),
(21, 14, 'drive', 'Exercices Électricité', 'https://drive.google.com/', '2025-12-14 19:21:24', NULL),
(22, 25, 'drive', 'grammaire', 'https://drive.google.com/drive/my-drive', '2025-12-14 19:32:03', NULL),
(23, 26, 'drive', 'writing', 'https://drive.google.com/drive/my-driveD', '2025-12-14 19:32:34', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

CREATE TABLE `utilisateur` (
  `id_utilisateur` int(11) NOT NULL,
  `nom` varchar(50) DEFAULT NULL,
  `prenom` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL,
  `role` enum('admin','eleve') DEFAULT 'eleve',
  `date_inscription` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_naissance` date NOT NULL,
  `numero` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id_utilisateur`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`, `date_inscription`, `date_naissance`, `numero`) VALUES
(2, 'belhadj', 'achref', 'admin@admin.com', '$2y$10$yjXuMarhZZ/50/PkWpEHK.vzfpfJ7MnerwSwnvDQ5uunWZfswa9je', 'admin', '2025-11-29 10:55:44', '0000-00-00', '22222333'),
(18, 'Ben Ali', 'Youssef', 'youssef.benali@bac.tn', '12345678', 'eleve', '2025-12-03 19:18:30', '2006-05-12', '22123456'),
(20, 'Gharbi', 'Sami', 'sami.gharbi@bac.tn', '12345678', 'eleve', '2025-12-02 19:18:30', '2006-08-02', '24123456'),
(21, 'Mejri', 'Amina', 'amina.mejri@bac.tn', '12345678', 'eleve', '2025-12-14 19:18:30', '2006-11-15', '25123456'),
(22, 'Admin', 'Sapientia', 'admin@sapientia.tn', 'admin123', 'admin', '2025-12-14 19:18:30', '2000-01-01', '20111111');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `chapitre`
--
ALTER TABLE `chapitre`
  ADD PRIMARY KEY (`id_chapitre`),
  ADD KEY `id_cours` (`id_cours`);

--
-- Index pour la table `chatbot_interaction`
--
ALTER TABLE `chatbot_interaction`
  ADD PRIMARY KEY (`id_interaction`),
  ADD KEY `id_utilisateur` (`id_utilisateur`);

--
-- Index pour la table `commentaire`
--
ALTER TABLE `commentaire`
  ADD PRIMARY KEY (`id_commentaire`),
  ADD KEY `id_utilisateur` (`id_utilisateur`),
  ADD KEY `id_ressource` (`id_ressource`);

--
-- Index pour la table `cours`
--
ALTER TABLE `cours`
  ADD PRIMARY KEY (`id_cours`);

--
-- Index pour la table `exercice`
--
ALTER TABLE `exercice`
  ADD PRIMARY KEY (`id_exercice`),
  ADD KEY `id_chapitre` (`id_chapitre`);

--
-- Index pour la table `exercice_progression`
--
ALTER TABLE `exercice_progression`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_utilisateur` (`id_utilisateur`),
  ADD KEY `id_exercice` (`id_exercice`);

--
-- Index pour la table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id_utilisateur` (`id_utilisateur`);

--
-- Index pour la table `parametresite`
--
ALTER TABLE `parametresite`
  ADD PRIMARY KEY (`id_parametre`);

--
-- Index pour la table `progression`
--
ALTER TABLE `progression`
  ADD PRIMARY KEY (`id_progression`),
  ADD KEY `id_utilisateur` (`id_utilisateur`),
  ADD KEY `progression_ibfk_2` (`id_cours`);

--
-- Index pour la table `ressource`
--
ALTER TABLE `ressource`
  ADD PRIMARY KEY (`id_ressource`),
  ADD KEY `id_chapitre` (`id_chapitre`);

--
-- Index pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`id_utilisateur`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `chapitre`
--
ALTER TABLE `chapitre`
  MODIFY `id_chapitre` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT pour la table `chatbot_interaction`
--
ALTER TABLE `chatbot_interaction`
  MODIFY `id_interaction` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commentaire`
--
ALTER TABLE `commentaire`
  MODIFY `id_commentaire` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `cours`
--
ALTER TABLE `cours`
  MODIFY `id_cours` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `exercice`
--
ALTER TABLE `exercice`
  MODIFY `id_exercice` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT pour la table `exercice_progression`
--
ALTER TABLE `exercice_progression`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT pour la table `logs`
--
ALTER TABLE `logs`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT pour la table `parametresite`
--
ALTER TABLE `parametresite`
  MODIFY `id_parametre` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `progression`
--
ALTER TABLE `progression`
  MODIFY `id_progression` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT pour la table `ressource`
--
ALTER TABLE `ressource`
  MODIFY `id_ressource` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id_utilisateur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `chapitre`
--
ALTER TABLE `chapitre`
  ADD CONSTRAINT `chapitre_ibfk_1` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`);

--
-- Contraintes pour la table `chatbot_interaction`
--
ALTER TABLE `chatbot_interaction`
  ADD CONSTRAINT `chatbot_interaction_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`);

--
-- Contraintes pour la table `commentaire`
--
ALTER TABLE `commentaire`
  ADD CONSTRAINT `commentaire_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`),
  ADD CONSTRAINT `commentaire_ibfk_2` FOREIGN KEY (`id_ressource`) REFERENCES `ressource` (`id_ressource`);

--
-- Contraintes pour la table `exercice`
--
ALTER TABLE `exercice`
  ADD CONSTRAINT `exercice_ibfk_1` FOREIGN KEY (`id_chapitre`) REFERENCES `chapitre` (`id_chapitre`);

--
-- Contraintes pour la table `exercice_progression`
--
ALTER TABLE `exercice_progression`
  ADD CONSTRAINT `exercice_progression_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`),
  ADD CONSTRAINT `exercice_progression_ibfk_2` FOREIGN KEY (`id_exercice`) REFERENCES `exercice` (`id_exercice`);

--
-- Contraintes pour la table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`);

--
-- Contraintes pour la table `progression`
--
ALTER TABLE `progression`
  ADD CONSTRAINT `progression_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`),
  ADD CONSTRAINT `progression_ibfk_2` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`);

--
-- Contraintes pour la table `ressource`
--
ALTER TABLE `ressource`
  ADD CONSTRAINT `ressource_ibfk_1` FOREIGN KEY (`id_chapitre`) REFERENCES `chapitre` (`id_chapitre`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
