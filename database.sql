/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `activities` WRITE;
/*!40000 ALTER TABLE `activities` DISABLE KEYS */;
INSERT INTO `activities` VALUES (1,4,'Mengubah status tugas #5 menjadi DONE','todo',5,NULL,'2026-03-22 19:02:35'),(2,4,'Menambahkan tugas baru: tes','todo',7,NULL,'2026-03-22 19:03:16'),(3,4,'Mengubah status tugas #7 menjadi TODO','todo',7,NULL,'2026-03-22 19:10:08'),(4,4,'Mengubah status tugas #6 menjadi IN_PROGRESS','todo',6,NULL,'2026-03-22 19:10:11'),(5,4,'Mengubah status penyelesaian tugas','todo',6,NULL,'2026-03-22 19:11:39'),(6,4,'Mengubah status tugas menjadi IN_PROGRESS','todo',7,NULL,'2026-03-22 19:14:30'),(7,4,'Mengubah status tugas #7 menjadi DONE','todo',7,NULL,'2026-03-22 19:15:04'),(8,4,'Mengubah status tugas #6 menjadi IN_PROGRESS','todo',6,NULL,'2026-03-22 19:15:37'),(9,4,'Mengubah status tugas #7 menjadi TODO','todo',7,NULL,'2026-03-22 19:17:01'),(10,4,'Mengubah status tugas #7 menjadi TODO','todo',7,NULL,'2026-03-22 19:17:18'),(11,4,'Mengubah status tugas menjadi DONE','todo',7,NULL,'2026-03-24 02:19:17'),(12,4,'Mengubah status tugas menjadi DONE','todo',6,NULL,'2026-03-24 02:19:22'),(13,4,'Menghapus tugas','todo',7,NULL,'2026-03-24 02:19:29'),(14,4,'Menambahkan tugas baru: tes','todo',8,NULL,'2026-03-24 02:19:37'),(15,4,'Memperbarui catatan: test','note',4,NULL,'2026-03-24 02:20:08'),(16,4,'Memperbarui pengaturan sistem','settings',NULL,NULL,'2026-03-24 02:43:02'),(17,4,'Memperbarui pengaturan sistem','settings',NULL,NULL,'2026-03-24 02:53:06'),(18,4,'Mengubah status tugas menjadi DONE','todo',8,NULL,'2026-03-24 02:56:15'),(19,4,'Menambahkan tugas baru: tes2','todo',9,NULL,'2026-03-24 02:56:23'),(20,4,'Mengubah status tugas menjadi TODO','todo',8,NULL,'2026-03-24 02:56:54'),(21,4,'Mengubah status tugas menjadi IN_PROGRESS','todo',9,NULL,'2026-03-24 02:57:00'),(22,4,'Mengubah status tugas menjadi IN_PROGRESS','todo',8,NULL,'2026-03-24 02:57:04'),(23,4,'Memperbarui pengaturan sistem','settings',NULL,NULL,'2026-03-24 02:58:18'),(24,4,'Mengubah status tugas menjadi DONE','todo',8,NULL,'2026-03-24 04:26:43'),(25,4,'Mengubah status tugas #9 menjadi DONE','todo',9,NULL,'2026-03-24 04:26:51'),(26,4,'Membuat catatan baru: test2','note',5,NULL,'2026-03-24 04:27:13'),(27,4,'Memperbarui pengaturan sistem','settings',NULL,NULL,'2026-03-24 09:55:04'),(28,4,'Memperbarui pengaturan sistem','settings',NULL,NULL,'2026-03-24 09:55:25'),(29,4,'Memperbarui pengaturan sistem','settings',NULL,NULL,'2026-03-24 09:55:30'),(30,4,'Memperbarui pengaturan sistem','settings',NULL,NULL,'2026-03-24 09:55:39'),(31,4,'Menambahkan tugas baru: test','todo',10,'{\"date\":\"2026-03-28\"}','2026-03-27 12:39:55'),(32,4,'Menambahkan tugas baru: test','todo',11,'{\"date\":\"2026-03-28\"}','2026-03-27 12:39:59'),(33,4,'Menambahkan tugas baru: testkjsahfhjewf','todo',12,'{\"date\":\"2026-03-30\"}','2026-03-27 12:40:09');
/*!40000 ALTER TABLE `activities` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `tags` text DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_type` (`user_id`,`file_type`),
  KEY `idx_documents_user_id` (`user_id`),
  KEY `idx_documents_uploaded` (`upload_date`),
  FULLTEXT KEY `idx_tags_name` (`name`,`tags`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,1,'gfh',NULL,'image/png',278583,'thth','2026-03-14 07:11:36'),(2,1,'ny','uploads/1/1773472619_Emergency-QR-Bambang.png','image/png',4674,'yny','2026-03-14 07:16:59'),(3,1,'tb','uploads/1/1773472653_p ilo license.docx','application/vnd.openxmlformats-officedocument.word',13903,'tbt','2026-03-14 07:17:33'),(4,3,'thth','uploads/3/1773473634_Emergency-ID-Card-Habib Frambudi.png','image/png',278583,'tht','2026-03-14 07:33:54'),(5,1,'sxsx','uploads/1/1773490155_4.jpeg','image/jpeg',59253,'wxw','2026-03-14 12:09:15'),(6,4,'afaf','uploads/4/1774201781_Surat Keterangan Domisili - habib frambudi.pdf','application/pdf',278828,'afa','2026-03-22 17:49:41'),(7,4,'thth','uploads/4/1774202812_ttd tes.png','image/png',1978,'thth','2026-03-22 18:06:52'),(8,4,'myumumu','uploads/4/1774202833_pdf-test (14).pdf','application/pdf',20597,'umum','2026-03-22 18:07:13');
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (2,4,3,'tes',1,'2026-03-24 07:21:06',NULL),(3,4,3,'a',1,'2026-03-24 07:22:04',NULL),(4,4,3,'sdd',1,'2026-03-24 07:22:23',NULL),(5,4,3,'jancok',1,'2026-03-24 07:22:34',NULL),(6,3,4,'p',1,'2026-03-24 07:26:28',NULL),(7,3,4,'pantek',1,'2026-03-24 07:26:33',NULL),(10,4,3,'sdsd',1,'2026-03-24 07:29:11',NULL),(11,4,3,'asas',1,'2026-03-24 07:29:14',NULL),(12,4,3,'xcxc',1,'2026-03-24 07:30:42',NULL),(13,4,3,'',1,'2026-03-24 07:31:32','1774337492_69c23dd4df5f0.png'),(14,4,3,'asas',1,'2026-03-24 07:33:07',NULL),(15,4,3,'asssd',1,'2026-03-24 07:33:31',NULL),(16,3,4,'dgdg',1,'2026-03-24 07:33:55',NULL),(17,3,4,'ggj',1,'2026-03-24 07:33:59',NULL),(18,4,3,'dfgdfg',1,'2026-03-24 09:29:06',NULL),(19,4,3,'sdfdsf',1,'2026-03-24 09:31:31',NULL),(20,4,3,'fdghfgh',1,'2026-03-24 09:31:33',NULL),(21,4,3,'dsvsdvd',1,'2026-03-24 09:31:54',NULL),(22,4,3,'dsfgdg',1,'2026-03-24 09:31:59',NULL),(23,4,3,'ddgdg',1,'2026-03-24 09:32:14',NULL),(24,4,3,'adadadasdgfg',1,'2026-03-24 09:35:11',NULL),(25,4,3,'assf',1,'2026-03-24 09:35:17',NULL),(26,4,3,'hk',1,'2026-03-24 09:35:29',NULL);
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `category` varchar(50) DEFAULT 'Ide',
  `color` varchar(20) DEFAULT 'violet',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_updated` (`user_id`,`updated_at`),
  KEY `idx_notes_user_id` (`user_id`),
  KEY `idx_notes_updated` (`updated_at`),
  CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `notes` WRITE;
/*!40000 ALTER TABLE `notes` DISABLE KEYS */;
INSERT INTO `notes` VALUES (1,1,'fgh','fgh','2026-03-14 07:01:20','2026-03-14 07:01:20'),(2,1,'ergerg','erger','2026-03-14 07:07:58','2026-03-14 07:07:58'),(3,3,'tht','tht','2026-03-14 07:33:46','2026-03-14 07:33:46'),(4,4,'test','test1','2026-03-24 02:20:07','2026-03-22 17:49:20'),(5,4,'test2','testtt','2026-03-24 04:27:13','2026-03-24 04:27:13');
/*!40000 ALTER TABLE `notes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('allow_registration','0','2026-03-24 02:43:02'),('app_logo','','2026-03-22 18:58:24'),('app_name','TaskFlow Pro','2026-03-22 18:58:24'),('app_url','','2026-03-24 09:55:04'),('enable_chat','0','2026-03-24 09:55:39'),('notif_time','08','2026-03-24 09:55:04'),('smtp_encrypt','tls','2026-03-24 02:55:47'),('smtp_from','omitkno@gmail.com','2026-03-24 02:58:18'),('smtp_host','','2026-03-24 02:55:47'),('smtp_pass','oneAVIoneKNO','2026-03-24 02:58:18'),('smtp_port','587','2026-03-24 02:55:47'),('smtp_user','omitkno@gmail.com','2026-03-24 02:58:18'),('telegram_bot_token','8746546081:AAFZ9rWFksJqaaAY6Atenp8bkv4nNlwQO3Y','2026-03-24 02:43:02');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `todos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `todos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `todo_date` date NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `status` varchar(20) DEFAULT 'todo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`,`todo_date`),
  KEY `idx_completed` (`completed`),
  KEY `idx_todos_user_id` (`user_id`),
  KEY `idx_todos_date` (`todo_date`),
  KEY `idx_todos_completed` (`completed`),
  CONSTRAINT `todos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `todos` WRITE;
/*!40000 ALTER TABLE `todos` DISABLE KEYS */;
INSERT INTO `todos` VALUES (1,1,'tes','2026-03-14',0,'todo','2026-03-14 07:01:12'),(2,1,'dfgdfg','2026-03-14',0,'todo','2026-03-14 07:06:11'),(3,1,'efgtgrgreg','2026-03-14',0,'todo','2026-03-14 07:07:49'),(4,3,'tht','2026-03-14',0,'todo','2026-03-14 07:33:37'),(5,4,'gfegrgre','2026-03-22',1,'done','2026-03-22 17:49:01'),(6,4,'afaf','2026-03-23',1,'done','2026-03-22 17:49:28'),(8,4,'tes','2026-03-24',1,'done','2026-03-24 02:19:37'),(9,4,'tes2','2026-03-24',1,'done','2026-03-24 02:56:23'),(10,4,'test','2026-03-28',0,'todo','2026-03-27 12:39:55'),(11,4,'test','2026-03-28',0,'todo','2026-03-27 12:39:59'),(12,4,'testkjsahfhjewf','2026-03-30',0,'todo','2026-03-27 12:40:09');
/*!40000 ALTER TABLE `todos` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `role` enum('administrator','user') DEFAULT 'user',
  `profile_photo` varchar(255) DEFAULT NULL,
  `telegram_chat_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_email` (`email`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin@taskflow.com','$2y$10$0QCmBLUj4z.o6M1DdXXhM.HhXy0c767UzhHU38yi7m0eP6.VQAuEq','Administrator','administrator','profile_1_1774201038.jpg',NULL,'2026-03-14 06:46:51'),(3,'user@gmail.com','$2y$10$Ytag13Bu0fxu0VdFTmRCCebSNoxXmUeuiZeyQtBxoRZLh4.x8Djjy','user','user',NULL,NULL,'2026-03-14 07:21:08'),(4,'frambudihabib@gmail.com','$2y$10$d8f7ZYIJLCFijdeamuOmi.Bfm4H8g6zATa88XHt9OsByjnfL/bdQa','Habib Frambudi','administrator','profile_4_1774201689.png','6244477500','2026-03-22 17:42:57'),(5,'alfon@gmail.com','$2y$10$4kJRHBcsmxaBe4cWeLqi8.pf7uSp3N5U83WBS1XkxAYfQKtrbAumu','alfon','user','profile_5_1774205693.png',NULL,'2026-03-22 18:42:17');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `document_shares`;
CREATE TABLE `document_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `shared_with_user_id` int(11) NOT NULL,
  `shared_by_user_id` int(11) NOT NULL,
  `permission` varchar(20) DEFAULT 'view',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_doc_share` (`document_id`,`shared_with_user_id`),
  KEY `shared_with_user_id` (`shared_with_user_id`),
  KEY `shared_by_user_id` (`shared_by_user_id`),
  CONSTRAINT `doc_share_doc_fk` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doc_share_user_fk` FOREIGN KEY (`shared_with_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doc_share_by_fk` FOREIGN KEY (`shared_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `document_public_shares`;
CREATE TABLE `document_public_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `share_token` varchar(64) NOT NULL,
  `created_by` int(11) NOT NULL,
  `expires_at` datetime NOT NULL,
  `views_count` int(11) NOT NULL DEFAULT 0,
  `downloads_count` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_public_token` (`share_token`),
  KEY `idx_public_doc` (`document_id`),
  KEY `idx_public_user` (`created_by`),
  KEY `idx_public_expires` (`expires_at`),
  CONSTRAINT `pub_share_doc_fk` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pub_share_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_projects` (`user_id`),
  CONSTRAINT `projects_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


