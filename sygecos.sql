-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 01 juil. 2025 à 16:02
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `validmaster`
--

-- --------------------------------------------------------

--
-- Structure de la table `affecter`
--

DROP TABLE IF EXISTS `affecter`;
CREATE TABLE IF NOT EXISTS `affecter` (
  `mat_util` varchar(20) NOT NULL,
  `id_rapp` int NOT NULL,
  `id_jury` int NOT NULL,
  PRIMARY KEY (`mat_util`,`id_rapp`,`id_jury`),
  KEY `id_rapp` (`id_rapp`),
  KEY `id_jury` (`id_jury`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ann_acad`
--

DROP TABLE IF EXISTS `ann_acad`;
CREATE TABLE IF NOT EXISTS `ann_acad` (
  `id_ann` int NOT NULL AUTO_INCREMENT,
  `dte_deb` date NOT NULL,
  `dte_fin` date NOT NULL,
  PRIMARY KEY (`id_ann`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `approuverrapport`
--

DROP TABLE IF EXISTS `approuverrapport`;
CREATE TABLE IF NOT EXISTS `approuverrapport` (
  `mat_util` varchar(20) NOT NULL,
  `id_rapp` int NOT NULL,
  `dte_approb` date NOT NULL,
  `com_approb` text,
  PRIMARY KEY (`mat_util`,`id_rapp`),
  KEY `id_rapp` (`id_rapp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `compterendu`
--

DROP TABLE IF EXISTS `compterendu`;
CREATE TABLE IF NOT EXISTS `compterendu` (
  `id_cr` int NOT NULL AUTO_INCREMENT,
  `nom_cr` varchar(40) NOT NULL,
  `date_cr` date NOT NULL,
  `dte_env` varchar(50) DEFAULT NULL,
  `mat_util` varchar(20) NOT NULL,
  PRIMARY KEY (`id_cr`),
  KEY `mat_util` (`mat_util`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `couter`
--

DROP TABLE IF EXISTS `couter`;
CREATE TABLE IF NOT EXISTS `couter` (
  `id_niv` int NOT NULL,
  `id_ann` int NOT NULL,
  `prix_niv` int NOT NULL,
  PRIMARY KEY (`id_niv`,`id_ann`),
  KEY `id_ann` (`id_ann`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ecue`
--

DROP TABLE IF EXISTS `ecue`;
CREATE TABLE IF NOT EXISTS `ecue` (
  `id_ecue` int NOT NULL AUTO_INCREMENT,
  `lib_ecue` varchar(25) NOT NULL,
  `cred_ecue` int NOT NULL,
  `id_ue` int NOT NULL,
  PRIMARY KEY (`id_ecue`),
  UNIQUE KEY `lib_ecue` (`lib_ecue`),
  KEY `id_ue` (`id_ue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `enseignant`
--

DROP TABLE IF EXISTS `enseignant`;
CREATE TABLE IF NOT EXISTS `enseignant` (
  `mat_util` varchar(20) NOT NULL,
  `dte_grade` date NOT NULL,
  `dte_fonc` date NOT NULL,
  `id_spec` int NOT NULL,
  `id_grade` int DEFAULT NULL,
  PRIMARY KEY (`mat_util`),
  KEY `id_spec` (`id_spec`),
  KEY `id_grade` (`id_grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `entreprise`
--

DROP TABLE IF EXISTS `entreprise`;
CREATE TABLE IF NOT EXISTS `entreprise` (
  `id_entr` int NOT NULL AUTO_INCREMENT,
  `lib_entr` varchar(50) NOT NULL,
  PRIMARY KEY (`id_entr`),
  UNIQUE KEY `lib_entr` (`lib_entr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `etudiant`
--

DROP TABLE IF EXISTS `etudiant`;
CREATE TABLE IF NOT EXISTS `etudiant` (
  `mat_util` varchar(20) NOT NULL,
  `Niveau_Etudiant` varchar(9) NOT NULL,
  `dte_deb_stage` date DEFAULT NULL,
  `id_rapp` int DEFAULT NULL,
  `id_entr` int DEFAULT NULL,
  PRIMARY KEY (`mat_util`),
  KEY `id_rapp` (`id_rapp`),
  KEY `id_entr` (`id_entr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `evaluer`
--

DROP TABLE IF EXISTS `evaluer`;
CREATE TABLE IF NOT EXISTS `evaluer` (
  `mat_util` varchar(20) NOT NULL,
  `mat_util_1` varchar(20) NOT NULL,
  `id_ecue` int NOT NULL,
  `dte_eval` date NOT NULL,
  `note` decimal(4,2) NOT NULL,
  PRIMARY KEY (`mat_util`,`mat_util_1`,`id_ecue`),
  KEY `mat_util_1` (`mat_util_1`),
  KEY `id_ecue` (`id_ecue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `fonction`
--

DROP TABLE IF EXISTS `fonction`;
CREATE TABLE IF NOT EXISTS `fonction` (
  `id_fonc` int NOT NULL AUTO_INCREMENT,
  `lib_fonc` varchar(100) NOT NULL,
  PRIMARY KEY (`id_fonc`),
  UNIQUE KEY `lib_fonc` (`lib_fonc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `grade`
--

DROP TABLE IF EXISTS `grade`;
CREATE TABLE IF NOT EXISTS `grade` (
  `id_grade` int NOT NULL AUTO_INCREMENT,
  `lib_grade` varchar(30) NOT NULL,
  PRIMARY KEY (`id_grade`),
  UNIQUE KEY `lib_grade` (`lib_grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `groupeutil`
--

DROP TABLE IF EXISTS `groupeutil`;
CREATE TABLE IF NOT EXISTS `groupeutil` (
  `id_groupe` int NOT NULL AUTO_INCREMENT,
  `lib_groupe` varchar(50) NOT NULL,
  PRIMARY KEY (`id_groupe`),
  UNIQUE KEY `lib_groupe` (`lib_groupe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `inscrire`
--

DROP TABLE IF EXISTS `inscrire`;
CREATE TABLE IF NOT EXISTS `inscrire` (
  `mat_util` varchar(20) NOT NULL,
  `id_niv` int NOT NULL,
  `id_ann` int NOT NULL,
  `dte_insc` date NOT NULL,
  PRIMARY KEY (`mat_util`,`id_niv`,`id_ann`),
  KEY `id_niv` (`id_niv`),
  KEY `id_ann` (`id_ann`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `niveau`
--

DROP TABLE IF EXISTS `niveau`;
CREATE TABLE IF NOT EXISTS `niveau` (
  `id_niv` int NOT NULL AUTO_INCREMENT,
  `lib_niv` varchar(15) NOT NULL,
  PRIMARY KEY (`id_niv`),
  UNIQUE KEY `lib_niv` (`lib_niv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `niveauacces`
--

DROP TABLE IF EXISTS `niveauacces`;
CREATE TABLE IF NOT EXISTS `niveauacces` (
  `id_acclev` int NOT NULL AUTO_INCREMENT,
  `lib_acclev` varchar(50) NOT NULL,
  PRIMARY KEY (`id_acclev`),
  UNIQUE KEY `lib_acclev` (`lib_acclev`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `niveauapprobation`
--

DROP TABLE IF EXISTS `niveauapprobation`;
CREATE TABLE IF NOT EXISTS `niveauapprobation` (
  `id_appr` int NOT NULL AUTO_INCREMENT,
  `lib_appr` varchar(30) NOT NULL,
  PRIMARY KEY (`id_appr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `pers_admin`
--

DROP TABLE IF EXISTS `pers_admin`;
CREATE TABLE IF NOT EXISTS `pers_admin` (
  `mat_util` varchar(20) NOT NULL,
  `dte_fonc` date NOT NULL,
  PRIMARY KEY (`mat_util`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `pister`
--

DROP TABLE IF EXISTS `pister`;
CREATE TABLE IF NOT EXISTS `pister` (
  `mat_util` varchar(20) NOT NULL,
  `id_trait` int NOT NULL,
  `dteH_trait` datetime NOT NULL,
  PRIMARY KEY (`mat_util`,`id_trait`),
  KEY `id_trait` (`id_trait`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `posseder`
--

DROP TABLE IF EXISTS `posseder`;
CREATE TABLE IF NOT EXISTS `posseder` (
  `mat_util` varchar(20) NOT NULL,
  `id_groupe` int NOT NULL,
  PRIMARY KEY (`mat_util`,`id_groupe`),
  KEY `id_groupe` (`id_groupe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rapportetud`
--

DROP TABLE IF EXISTS `rapportetud`;
CREATE TABLE IF NOT EXISTS `rapportetud` (
  `id_rapp` int NOT NULL AUTO_INCREMENT,
  `nom_rapp` varchar(40) NOT NULL,
  `date_rapp` date NOT NULL,
  `theme_mem` varchar(40) NOT NULL,
  PRIMARY KEY (`id_rapp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rattacher`
--

DROP TABLE IF EXISTS `rattacher`;
CREATE TABLE IF NOT EXISTS `rattacher` (
  `id_groupe` int NOT NULL,
  `id_trait` int NOT NULL,
  PRIMARY KEY (`id_groupe`,`id_trait`),
  KEY `id_trait` (`id_trait`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `soutenance`
--

DROP TABLE IF EXISTS `soutenance`;
CREATE TABLE IF NOT EXISTS `soutenance` (
  `id_sout` int NOT NULL,
  `dte_sout` date NOT NULL,
  `heure_sout` time NOT NULL,
  `salle` varchar(100) NOT NULL,
  `statut_sout` varchar(50) DEFAULT NULL,
  `id_rapp` int NOT NULL,
  PRIMARY KEY (`id_sout`),
  KEY `id_rapp` (`id_rapp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `specialite`
--

DROP TABLE IF EXISTS `specialite`;
CREATE TABLE IF NOT EXISTS `specialite` (
  `id_spec` int NOT NULL AUTO_INCREMENT,
  `lib_spec` varchar(50) NOT NULL,
  PRIMARY KEY (`id_spec`),
  UNIQUE KEY `lib_spec` (`lib_spec`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `statut_jury`
--

DROP TABLE IF EXISTS `statut_jury`;
CREATE TABLE IF NOT EXISTS `statut_jury` (
  `id_jury` int NOT NULL AUTO_INCREMENT,
  `lib_jury` varchar(50) NOT NULL,
  PRIMARY KEY (`id_jury`),
  UNIQUE KEY `lib_jury` (`lib_jury`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `traitement`
--

DROP TABLE IF EXISTS `traitement`;
CREATE TABLE IF NOT EXISTS `traitement` (
  `id_trait` int NOT NULL AUTO_INCREMENT,
  `lib_trait` varchar(50) NOT NULL,
  PRIMARY KEY (`id_trait`),
  UNIQUE KEY `lib_trait` (`lib_trait`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ue`
--

DROP TABLE IF EXISTS `ue`;
CREATE TABLE IF NOT EXISTS `ue` (
  `id_ue` int NOT NULL AUTO_INCREMENT,
  `lib_ue` varchar(25) NOT NULL,
  `cred_ue` int NOT NULL,
  PRIMARY KEY (`id_ue`),
  UNIQUE KEY `lib_ue` (`lib_ue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
CREATE TABLE IF NOT EXISTS `utilisateur` (
  `mat_util` varchar(20) NOT NULL,
  `nom_util` varchar(30) NOT NULL,
  `pren_util` varchar(100) NOT NULL,
  `naiss_util` date NOT NULL,
  `mdp_util` varchar(30) NOT NULL,
  `mail_util` varchar(50) NOT NULL,
  `temp_password` varchar(50) DEFAULT NULL,
  `id_acclev` int DEFAULT NULL,
  `id_fonc` int NOT NULL,
  PRIMARY KEY (`mat_util`),
  KEY `id_acclev` (`id_acclev`),
  KEY `id_fonc` (`id_fonc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `validerrapport`
--

DROP TABLE IF EXISTS `validerrapport`;
CREATE TABLE IF NOT EXISTS `validerrapport` (
  `mat_util` varchar(20) NOT NULL,
  `id_rapp` int NOT NULL,
  `dte_valid` date NOT NULL,
  `com_valid` text,
  PRIMARY KEY (`mat_util`,`id_rapp`),
  KEY `id_rapp` (`id_rapp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `affecter`
--
ALTER TABLE `affecter`
  ADD CONSTRAINT `affecter_ibfk_1` FOREIGN KEY (`mat_util`) REFERENCES `enseignant` (`mat_util`),
  ADD CONSTRAINT `affecter_ibfk_2` FOREIGN KEY (`id_rapp`) REFERENCES `rapportetud` (`id_rapp`),
  ADD CONSTRAINT `affecter_ibfk_3` FOREIGN KEY (`id_jury`) REFERENCES `statut_jury` (`id_jury`);

--
-- Contraintes pour la table `approuverrapport`
--
ALTER TABLE `approuverrapport`
  ADD CONSTRAINT `approuverrapport_ibfk_1` FOREIGN KEY (`mat_util`) REFERENCES `enseignant` (`mat_util`),
  ADD CONSTRAINT `approuverrapport_ibfk_2` FOREIGN KEY (`id_rapp`) REFERENCES `rapportetud` (`id_rapp`);

--
-- Contraintes pour la table `compterendu`
--
ALTER TABLE `compterendu`
  ADD CONSTRAINT `compterendu_ibfk_1` FOREIGN KEY (`mat_util`) REFERENCES `enseignant` (`mat_util`);

--
-- Contraintes pour la table `couter`
--
ALTER TABLE `couter`
  ADD CONSTRAINT `couter_ibfk_1` FOREIGN KEY (`id_niv`) REFERENCES `niveau` (`id_niv`),
  ADD CONSTRAINT `couter_ibfk_2` FOREIGN KEY (`id_ann`) REFERENCES `ann_acad` (`id_ann`);

--
-- Contraintes pour la table `ecue`
--
ALTER TABLE `ecue`
  ADD CONSTRAINT `ecue_ibfk_1` FOREIGN KEY (`id_ue`) REFERENCES `ue` (`id_ue`);

--
-- Contraintes pour la table `enseignant`
--
ALTER TABLE `enseignant`
  ADD CONSTRAINT `enseignant_ibfk_1` FOREIGN KEY (`mat_util`) REFERENCES `utilisateur` (`mat_util`),
  ADD CONSTRAINT `enseignant_ibfk_2` FOREIGN KEY (`id_spec`) REFERENCES `specialite` (`id_spec`),
  ADD CONSTRAINT `enseignant_ibfk_3` FOREIGN KEY (`id_grade`) REFERENCES `grade` (`id_grade`);

--
-- Contraintes pour la table `etudiant`
--
ALTER TABLE `etudiant`
  ADD CONSTRAINT `etudiant_ibfk_1` FOREIGN KEY (`mat_util`) REFERENCES `utilisateur` (`mat_util`),
  ADD CONSTRAINT `etudiant_ibfk_2` FOREIGN KEY (`id_rapp`) REFERENCES `rapportetud` (`id_rapp`),
  ADD CONSTRAINT `etudiant_ibfk_3` FOREIGN KEY (`id_entr`) REFERENCES `entreprise` (`id_entr`);

--
-- Contraintes pour la table `evaluer`
--
ALTER TABLE `evaluer`
  ADD CONSTRAINT `evaluer_ibfk_1` FOREIGN KEY (`mat_util`) REFERENCES `etudiant` (`mat_util`),
  ADD CONSTRAINT `evaluer_ibfk_2` FOREIGN KEY (`mat_util_1`) REFERENCES `enseignant` (`mat_util`),
  ADD CONSTRAINT `evaluer_ibfk_3` FOREIGN KEY (`id_ecue`) REFERENCES `ecue` (`id_ecue`);

--
-- Contraintes pour la table `inscrire`
--
ALTER TABLE `inscrire`
  ADD CONSTRAINT `inscrire_ibfk_1` FOREIGN KEY (`mat_util`) REFERENCES `etudiant` (`mat_util`),
  ADD CONSTRAINT `inscrire_ibfk_2` FOREIGN KEY (`id_niv`) REFERENCES `niveau` (`id_niv`),
  ADD CONSTRAINT `inscrire_ibfk_3` FOREIGN KEY (`id_ann`) REFERENCES `ann_acad` (`id_ann`);

--
-- Contraintes pour la table `pers_admin`
--
ALTER TABLE `pers_admin`
  ADD CONSTRAINT `pers_admin_ibfk_1` FOREIGN KEY (`mat_util`) REFERENCES `utilisateur` (`mat_util`);

--
-- Contraintes pour la table `pister`
--
ALTER TABLE `pister`
  ADD CONSTRAINT `pister_ibfk_1` FOREIGN KEY (`mat_util`) REFERENCES `utilisateur` (`mat_util`),
  ADD CONSTRAINT `pister_ibfk_2` FOREIGN KEY (`id_trait`) REFERENCES `traitement` (`id_trait`);

--
-- Contraintes pour la table `posseder`
--
ALTER TABLE `posseder`
  ADD CONSTRAINT `posseder_ibfk_1` FOREIGN KEY (`mat_util`) REFERENCES `utilisateur` (`mat_util`),
  ADD CONSTRAINT `posseder_ibfk_2` FOREIGN KEY (`id_groupe`) REFERENCES `groupeutil` (`id_groupe`);

--
-- Contraintes pour la table `rattacher`
--
ALTER TABLE `rattacher`
  ADD CONSTRAINT `rattacher_ibfk_1` FOREIGN KEY (`id_groupe`) REFERENCES `groupeutil` (`id_groupe`),
  ADD CONSTRAINT `rattacher_ibfk_2` FOREIGN KEY (`id_trait`) REFERENCES `traitement` (`id_trait`);

--
-- Contraintes pour la table `soutenance`
--
ALTER TABLE `soutenance`
  ADD CONSTRAINT `soutenance_ibfk_1` FOREIGN KEY (`id_rapp`) REFERENCES `rapportetud` (`id_rapp`);

--
-- Contraintes pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD CONSTRAINT `utilisateur_ibfk_1` FOREIGN KEY (`id_acclev`) REFERENCES `niveauacces` (`id_acclev`),
  ADD CONSTRAINT `utilisateur_ibfk_2` FOREIGN KEY (`id_fonc`) REFERENCES `fonction` (`id_fonc`);

--
-- Contraintes pour la table `validerrapport`
--
ALTER TABLE `validerrapport`
  ADD CONSTRAINT `validerrapport_ibfk_1` FOREIGN KEY (`mat_util`) REFERENCES `enseignant` (`mat_util`),
  ADD CONSTRAINT `validerrapport_ibfk_2` FOREIGN KEY (`id_rapp`) REFERENCES `rapportetud` (`id_rapp`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
