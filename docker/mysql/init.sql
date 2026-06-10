-- Transport ANCF - Initial MySQL Setup
-- This file runs automatically when the MySQL container is first created

CREATE DATABASE IF NOT EXISTS ancf_transport CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS ancf_transport_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON ancf_transport.* TO 'ancf_user'@'%';
GRANT ALL PRIVILEGES ON ancf_transport_test.* TO 'ancf_user'@'%';
FLUSH PRIVILEGES;
