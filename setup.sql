CREATE TABLE IF NOT EXISTS settings (
  id INT(11) PRIMARY KEY AUTO_INCREMENT,
  site_name VARCHAR(100) DEFAULT 'Elegant Venues',
  site_email VARCHAR(100),
  site_phone VARCHAR(20),
  site_address TEXT,
  booking_confirmation TINYINT(1) DEFAULT 1,
  admin_notifications TINYINT(1) DEFAULT 1,
  email_notifications TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (site_name, site_email, site_phone, site_address) 
VALUES ('Elegant Venues', 'admin@elegantvenues.com', '+923341513407', 'Zaman Khan Plaza 2nd Floor University Town Peshawar');

CREATE TABLE IF NOT EXISTS event_categories (
  id INT(11) PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  icon VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO event_categories (name, description, icon) 
VALUES 
('Wedding', 'Wedding events and celebrations', 'fa-ring'),
('Engagement', 'Engagement ceremonies', 'fa-heart'),
('Mehndi', 'Mehndi events', 'fa-palette'),
('Barat', 'Barat ceremonies', 'fa-horse'),
('Valima', 'Valima receptions', 'fa-utensils'),
('Corporate', 'Corporate events and conferences', 'fa-briefcase');
