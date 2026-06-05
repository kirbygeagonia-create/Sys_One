CREATE DATABASE IF NOT EXISTS skillloop;
USE skillloop;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    bio TEXT,
    avatar VARCHAR(255) DEFAULT NULL,
    location VARCHAR(100) DEFAULT NULL,
    availability TEXT DEFAULT NULL,
    credits INT NOT NULL DEFAULT 3,
    reputation DECIMAL(2,1) DEFAULT 0.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Skill categories
CREATE TABLE skill_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT 'fa-book'
);

-- Skills
CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (category_id) REFERENCES skill_categories(id) ON DELETE CASCADE
);

-- Skills a user offers to teach
CREATE TABLE user_skills_offered (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skill_id INT NOT NULL,
    proficiency ENUM('beginner', 'intermediate', 'advanced', 'expert') NOT NULL DEFAULT 'intermediate',
    description TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_offer (user_id, skill_id)
);

-- Skills a user wants to learn
CREATE TABLE user_skills_wanted (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skill_id INT NOT NULL,
    description TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_want (user_id, skill_id)
);

-- Session requests (initial proposal)
CREATE TABLE session_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    teacher_id INT NOT NULL,
    skill_id INT NOT NULL,
    message TEXT,
    status ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);

-- Confirmed sessions
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    scheduled_at DATETIME NOT NULL,
    duration INT NOT NULL DEFAULT 60,
    status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    requester_confirmed TINYINT(1) DEFAULT 0,
    teacher_confirmed TINYINT(1) DEFAULT 0,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (request_id) REFERENCES session_requests(id) ON DELETE CASCADE
);

-- Session reviews & ratings
CREATE TABLE session_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (session_id, reviewer_id)
);

-- Skill badges earned after session completion
CREATE TABLE badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    issuer_id INT NOT NULL,
    recipient_id INT NOT NULL,
    skill_id INT NOT NULL,
    level ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'beginner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (issuer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);

-- Credit transaction log
CREATE TABLE credit_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    counterparty_id INT DEFAULT NULL,
    amount INT NOT NULL,
    type ENUM('earn', 'spend', 'bonus') NOT NULL,
    reference_type VARCHAR(50) DEFAULT NULL,
    reference_id INT DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (counterparty_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    message VARCHAR(255) NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Login attempt tracking for IP-based rate limiting
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL DEFAULT 'login',
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_action_time (ip_address, action, attempt_time)
);

-- In-app messaging for session participants
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session (session_id, created_at),
    INDEX idx_unread (session_id, is_read, sender_id)
);

-- Performance indexes for common queries
CREATE INDEX idx_user_skills_offered_user ON user_skills_offered(user_id);
CREATE INDEX idx_user_skills_offered_skill ON user_skills_offered(skill_id);
CREATE INDEX idx_user_skills_wanted_user ON user_skills_wanted(user_id);
CREATE INDEX idx_credit_transactions_user ON credit_transactions(user_id, type, created_at);
CREATE INDEX idx_credit_transactions_ref ON credit_transactions(reference_type, reference_id);
CREATE INDEX idx_session_requests_teacher ON session_requests(teacher_id, status);
CREATE INDEX idx_session_requests_requester ON session_requests(requester_id, status);
CREATE INDEX idx_sessions_request ON sessions(request_id);
CREATE INDEX idx_sessions_status ON sessions(status, scheduled_at);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read, created_at);
CREATE INDEX idx_badges_recipient ON badges(recipient_id);
CREATE INDEX idx_password_reset_token ON password_reset_tokens(token);

-- Password reset tokens
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Database-level constraints
ALTER TABLE users ADD CONSTRAINT chk_credits_floor CHECK (credits >= 0);
ALTER TABLE users ADD CONSTRAINT chk_reputation CHECK (reputation BETWEEN 0.0 AND 5.0);
ALTER TABLE sessions ADD CONSTRAINT chk_duration CHECK (duration > 0);

-- Seed categories and skills
INSERT INTO skill_categories (name, icon) VALUES
('Music', 'fa-music'),
('Technology', 'fa-laptop-code'),
('Cooking', 'fa-utensils'),
('Art & Design', 'fa-palette'),
('Sports & Fitness', 'fa-dumbbell'),
('Languages', 'fa-globe'),
('Photography', 'fa-camera'),
('Writing', 'fa-pen-fancy'),
('Business', 'fa-briefcase'),
('Lifestyle', 'fa-star');

INSERT INTO skills (category_id, name) VALUES
(1, 'Guitar'), (1, 'Piano'), (1, 'Singing'), (1, 'Drums'), (1, 'Music Production'),
(2, 'PHP'), (2, 'JavaScript'), (2, 'Python'), (2, 'HTML/CSS'), (2, 'MySQL'), (2, 'React'), (2, 'Excel'),
(3, 'Baking'), (3, 'Meal Prep'), (3, 'Vegan Cooking'), (3, 'Grilling'), (3, 'Sushi Making'),
(4, 'Drawing'), (4, 'Painting'), (4, 'Digital Art'), (4, 'Graphic Design'), (4, 'UI/UX Design'),
(5, 'Yoga'), (5, 'Weight Training'), (5, 'Running'), (5, 'Swimming'), (5, 'Martial Arts'),
(6, 'English'), (6, 'Spanish'), (6, 'French'), (6, 'Japanese'), (6, 'Tagalog'),
(7, 'Portrait Photography'), (7, 'Photo Editing'), (7, 'Videography'),
(8, 'Creative Writing'), (8, 'Blogging'), (8, 'Copywriting'), (8, 'Poetry'),
(9, 'Public Speaking'), (9, 'Marketing'), (9, 'Accounting'), (9, 'Project Management'),
(10, 'Gardening'), (10, 'Sewing'), (10, 'Meditation'), (10, 'First Aid');