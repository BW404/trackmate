-- TrackMate Activity Logs Table
-- Stores AI-detected user activities from camera monitoring

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `activity_type` VARCHAR(100) NOT NULL,
  `category` TINYINT(1) NOT NULL COMMENT '1=Phone, 2=Working, 3=Phone+Work, 4=Sleeping, 5=Eating, 6=Drinking, 7=Other',
  `description` TEXT,
  `detected_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `detected_at` (`detected_at`),
  KEY `category` (`category`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create index for faster queries
CREATE INDEX idx_user_detected ON activity_logs(user_id, detected_at);
CREATE INDEX idx_category_detected ON activity_logs(category, detected_at);
