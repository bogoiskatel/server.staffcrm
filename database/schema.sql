CREATE TABLE IF NOT EXISTS installations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 name VARCHAR(200) NOT NULL,
 api_url VARCHAR(2048) NOT NULL,
 country_iso CHAR(2) NULL,
 country_calling_code VARCHAR(8) NULL,
 postcode VARCHAR(32) NULL,
 church_latitude DECIMAL(10,7) NULL,
 church_longitude DECIMAL(10,7) NULL,
 latitude DECIMAL(10,7) NULL,
 longitude DECIMAL(10,7) NULL,
 coordinate_source VARCHAR(32) NULL,
 coordinate_updated_at DATETIME NULL,
 registered_at DATETIME NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 CHECK(church_latitude IS NULL OR church_latitude BETWEEN -90 AND 90),
 CHECK(church_longitude IS NULL OR church_longitude BETWEEN -180 AND 180),
 CHECK(latitude IS NULL OR latitude BETWEEN -90 AND 90),
 CHECK(longitude IS NULL OR longitude BETWEEN -180 AND 180)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS installation_credentials (
 installation_id BIGINT UNSIGNED PRIMARY KEY,
 api_secret_encrypted MEDIUMTEXT NULL,
 phone_encrypted MEDIUMTEXT NULL,
 key_id VARCHAR(100) NULL,
 phone_verified TINYINT(1) NOT NULL DEFAULT 0,
 FOREIGN KEY(installation_id) REFERENCES installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS statistics_snapshots (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 installation_id BIGINT UNSIGNED NOT NULL,
 reporting_period CHAR(7) NOT NULL,
 generated_at DATETIME NOT NULL,
 collected_at DATETIME NOT NULL,
 contract_version VARCHAR(32) NOT NULL DEFAULT '1',
 members_total INT UNSIGNED NULL,
 baptized_this_month INT UNSIGNED NULL,
 baptized_this_year INT UNSIGNED NULL,
 joined_this_year INT UNSIGNED NULL,
 left_this_year INT UNSIGNED NULL,
 groups_current_season INT UNSIGNED NULL,
 discipline_total INT UNSIGNED NULL,
 last_successful_login DATETIME NULL,
 source_metadata MEDIUMTEXT NULL,
 UNIQUE KEY installation_period(installation_id, reporting_period),
 FOREIGN KEY(installation_id) REFERENCES installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS collection_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 installation_id BIGINT UNSIGNED NOT NULL,
 reporting_period CHAR(7) NOT NULL,
 attempted_at DATETIME NOT NULL,
 success TINYINT(1) NOT NULL,
 error_code VARCHAR(64) NULL,
 INDEX latest_attempt(installation_id, attempted_at, id),
 FOREIGN KEY(installation_id) REFERENCES installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS geocoding_cache (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 country_iso CHAR(2) NOT NULL,
 postcode VARCHAR(32) NOT NULL,
 provider VARCHAR(100) NOT NULL,
 latitude DECIMAL(10,7) NULL,
 longitude DECIMAL(10,7) NULL,
 status VARCHAR(32) NOT NULL,
 attribution VARCHAR(500) NULL,
 updated_at DATETIME NOT NULL,
 expires_at DATETIME NULL,
 UNIQUE KEY location_provider(country_iso, postcode, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 source_hash CHAR(64) NOT NULL,
 attempted_at DATETIME NOT NULL,
 INDEX source_time(source_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schema_migrations (
 version VARCHAR(64) PRIMARY KEY,
 applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS demo_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 source_ref VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 contact VARCHAR(200) NOT NULL,
 email VARCHAR(254) NOT NULL,
 phone VARCHAR(80) NOT NULL DEFAULT '',
 city VARCHAR(200) NOT NULL DEFAULT '',
 church VARCHAR(200) NOT NULL DEFAULT '',
 members VARCHAR(80) NOT NULL DEFAULT '',
 message TEXT NOT NULL,
 ip VARCHAR(45) NOT NULL DEFAULT '',
 order_date DATE NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'waiting',
 comment TEXT NOT NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX recent_orders(order_date,id),
 CHECK(status IN ('waiting','issued','paid','installed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
