CREATE DATABASE IF NOT EXISTS lost_found_db;
USE lost_found_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'staff', 'admin') DEFAULT 'student',
    phone VARCHAR(20),
    avatar VARCHAR(255) DEFAULT 'default.png',
    email_notifications BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified_at DATETIME NULL DEFAULT NULL,
    email_verification_token CHAR(64) NULL DEFAULT NULL,
    email_verification_expires DATETIME NULL DEFAULT NULL,
    email_verification_sent_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    icon VARCHAR(50) DEFAULT 'fa-tag',
    description VARCHAR(200),
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    type ENUM('lost', 'found') NOT NULL,
    location VARCHAR(200),
    building VARCHAR(100),
    date_occurred DATE,
    image VARCHAR(255),
    status ENUM('open', 'matched', 'claimed', 'returned', 'closed') DEFAULT 'open',
    is_resolved BOOLEAN DEFAULT FALSE,
    approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    approval_reviewed_at DATETIME NULL DEFAULT NULL,
    approval_reviewed_by INT NULL DEFAULT NULL,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (approval_reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_items_approval_status (approval_status)
);


CREATE TABLE claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    claimer_id INT NOT NULL,
    owner_id INT,
    message TEXT,
    proof_description TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (claimer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);


CREATE TABLE matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lost_item_id INT NOT NULL,
    found_item_id INT NOT NULL,
    similarity_score DECIMAL(5,2) DEFAULT 0,
    status ENUM('pending', 'notified', 'confirmed', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lost_item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (found_item_id) REFERENCES items(id) ON DELETE CASCADE
);

CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('match', 'claim', 'system', 'admin') DEFAULT 'system',
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE friendships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_one_id INT NOT NULL,
    user_two_id INT NOT NULL,
    requested_by_id INT NOT NULL,
    status ENUM('pending', 'accepted') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_friendship_pair (user_one_id, user_two_id),
    KEY idx_friendships_recipient (user_two_id, status),
    FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_one_id INT NOT NULL,
    user_two_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_conversation_pair (user_one_id, user_two_id),
    FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_messages_conversation (conversation_id, id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO categories (name, icon, description) VALUES
('Electronics', 'fa-laptop', 'Phones, laptops, tablets, earphones'),
('Documents', 'fa-file', 'IDs, cards, notebooks, papers'),
('Bags & Wallets', 'fa-briefcase', 'Backpacks, wallets, purses'),
('Clothing', 'fa-tshirt', 'Jackets, uniforms, accessories'),
('Keys', 'fa-key', 'House keys, car keys, keychains'),
('Books', 'fa-book', 'Textbooks, notebooks, stationery'),
('Personal Items', 'fa-user', 'Glasses, watches, jewelry'),
('Other', 'fa-ellipsis-h', 'Other miscellaneous items');

ALTER TABLE items
    ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
    ADD COLUMN approval_reviewed_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN approval_reviewed_by INT NULL DEFAULT NULL,
    ADD KEY idx_items_approval_status (approval_status),
    ADD CONSTRAINT fk_items_approval_reviewer
        FOREIGN KEY (approval_reviewed_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE items
    MODIFY COLUMN approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending';


CREATE TABLE IF NOT EXISTS friendships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_one_id INT NOT NULL,
    user_two_id INT NOT NULL,
    requested_by_id INT NOT NULL,
    status ENUM('pending', 'accepted') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_friendship_pair (user_one_id, user_two_id),
    KEY idx_friendships_recipient (user_two_id, status),
    CONSTRAINT fk_friendships_user_one FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_friendships_user_two FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_friendships_requester FOREIGN KEY (requested_by_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_one_id INT NOT NULL,
    user_two_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_conversation_pair (user_one_id, user_two_id),
    CONSTRAINT fk_conversations_user_one FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_conversations_user_two FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_messages_conversation (conversation_id, id),
    CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

