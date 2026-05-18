-- Drop existing tables if needed and recreate with proper schema

DROP TABLE IF EXISTS answer;
DROP TABLE IF EXISTS respondent;
DROP TABLE IF EXISTS form_generator_questions;
DROP TABLE IF EXISTS form_generator_event_sections;
DROP TABLE IF EXISTS form_generator_config;
DROP TABLE IF EXISTS form_generator_template_questions;
DROP TABLE IF EXISTS form_generator_template_sections;
DROP TABLE IF EXISTS form_generator_template;

-- Template Tables
CREATE TABLE form_generator_template (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE form_generator_template_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    section_title VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (template_id) REFERENCES form_generator_template(id) ON DELETE CASCADE
);

CREATE TABLE form_generator_template_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    section_id INT DEFAULT 0,
    question_text TEXT NOT NULL,
    question_type VARCHAR(50) NOT NULL DEFAULT 'text',
    is_required TINYINT(1) DEFAULT 1,
    options TEXT,
    sort_order INT DEFAULT 0,
    parent_question_id INT DEFAULT NULL,
    parent_option_value VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (template_id) REFERENCES form_generator_template(id) ON DELETE CASCADE
);

-- Event Tables
CREATE TABLE form_generator_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(100) UNIQUE NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    header_image VARCHAR(500),
    description TEXT,
    template_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES form_generator_template(id) ON DELETE SET NULL
);

CREATE TABLE form_generator_event_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(100) NOT NULL,
    section_title VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0
);

CREATE TABLE form_generator_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_event VARCHAR(100) NOT NULL,
    section_id INT DEFAULT 0,
    question_text TEXT NOT NULL,
    question_type VARCHAR(50) NOT NULL DEFAULT 'text',
    is_required TINYINT(1) DEFAULT 1,
    options TEXT,
    sort_order INT DEFAULT 0,
    parent_question_id INT DEFAULT NULL,
    parent_option_value VARCHAR(255) DEFAULT NULL
);

-- Response Tables
CREATE TABLE respondent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_event VARCHAR(100),
    full_name VARCHAR(255),
    email_1 VARCHAR(255),
    company_name VARCHAR(255),
    job_title VARCHAR(255),
    mobile_phone VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE answer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_feedback VARCHAR(100),
    id_respondent INT,
    id_question VARCHAR(255),
    answer_text TEXT,
    FOREIGN KEY (id_respondent) REFERENCES respondent(id) ON DELETE CASCADE
);