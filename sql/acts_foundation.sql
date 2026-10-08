-- ==================================================================
--  SHARAN FOUNDATION — CONSOLIDATED DATABASE INSTALL
--  Single-file install — covers all versions v1 through v9.
--  Safe to re-run: uses CREATE TABLE IF NOT EXISTS and INSERT IGNORE.
-- ==================================================================
--
-- TO INSTALL:
--   Option A (recommended): browse to /install.php — it runs this for you.
--   Option B (manual):      phpMyAdmin → Import → choose this file → Go
--
-- Default admin login (created by install.php):
--   Username: admin
--   Password: admin123  ← CHANGE THIS IMMEDIATELY AFTER FIRST LOGIN
-- ==================================================================

CREATE DATABASE IF NOT EXISTS `acts_foundation` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `acts_foundation`;

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
-- CORE TABLES
-- ============================================================

-- Schema version tracker — records every install/upgrade for audit + tooling
CREATE TABLE IF NOT EXISTS `schema_version` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `version`      VARCHAR(20) NOT NULL,
  `description`  VARCHAR(255),
  `applied_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `applied_by`   VARCHAR(100) DEFAULT 'installer',
  `notes`        TEXT,
  INDEX idx_version (`version`)
) ENGINE=InnoDB;

-- Admin users
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('superadmin','editor') DEFAULT 'editor',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
-- NOTE: install.php creates the default admin (password-hashed at runtime).

-- ============================================================
-- SITE SETTINGS (single row, id=1) — contains all global config
-- All optional columns added via separate ALTERs below for safety on upgrades.
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `site_title` VARCHAR(150) DEFAULT 'Sharan Foundation',
  `tagline` VARCHAR(200) DEFAULT 'Hope • Care • Transformation',
  `email_in` VARCHAR(150),
  `email_uk` VARCHAR(150),
  `phone_in` VARCHAR(50),
  `phone_uk` VARCHAR(50),
  `address_in` TEXT,
  `address_uk` TEXT,
  `facebook` VARCHAR(255),
  `instagram` VARCHAR(255),
  `twitter` VARCHAR(255),
  `youtube` VARCHAR(255),
  `about_short` TEXT,
  `mission` TEXT,
  `vision` TEXT,
  `values_text` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- SMTP / Email + Language (v2 + v6)
ALTER TABLE `settings`
  ADD COLUMN IF NOT EXISTS `smtp_host`             VARCHAR(150) DEFAULT 'smtp.gmail.com',
  ADD COLUMN IF NOT EXISTS `smtp_port`             INT          DEFAULT 587,
  ADD COLUMN IF NOT EXISTS `smtp_username`         VARCHAR(150) DEFAULT '',
  ADD COLUMN IF NOT EXISTS `smtp_password`         VARCHAR(255) DEFAULT '',
  ADD COLUMN IF NOT EXISTS `smtp_encryption`       ENUM('tls','ssl','none') DEFAULT 'tls',
  ADD COLUMN IF NOT EXISTS `smtp_from_email`       VARCHAR(150) DEFAULT 'noreply@sharanforall.org',
  ADD COLUMN IF NOT EXISTS `smtp_from_name`        VARCHAR(150) DEFAULT 'Sharan Foundation',
  ADD COLUMN IF NOT EXISTS `admin_notify_email`    VARCHAR(150) DEFAULT 'admin@sharanforall.org',
  ADD COLUMN IF NOT EXISTS `default_language`      VARCHAR(5)   DEFAULT 'en';

-- Payment gateway credentials (v6)
ALTER TABLE `settings`
  ADD COLUMN IF NOT EXISTS `gateway_mode`            ENUM('sandbox','live') DEFAULT 'sandbox',
  ADD COLUMN IF NOT EXISTS `razorpay_enabled`        TINYINT(1)   DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `razorpay_key_id`         VARCHAR(150) DEFAULT 'rzp_test_',
  ADD COLUMN IF NOT EXISTS `razorpay_key_secret`     VARCHAR(255) DEFAULT '',
  ADD COLUMN IF NOT EXISTS `razorpay_webhook_secret` VARCHAR(255) DEFAULT '',
  ADD COLUMN IF NOT EXISTS `stripe_enabled`          TINYINT(1)   DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `stripe_public_key`       VARCHAR(255) DEFAULT 'pk_test_',
  ADD COLUMN IF NOT EXISTS `stripe_secret_key`       VARCHAR(255) DEFAULT 'sk_test_',
  ADD COLUMN IF NOT EXISTS `stripe_webhook_secret`   VARCHAR(255) DEFAULT 'whsec_',
  ADD COLUMN IF NOT EXISTS `paypal_enabled`          TINYINT(1)   DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `paypal_client_id`        VARCHAR(255) DEFAULT '',
  ADD COLUMN IF NOT EXISTS `paypal_client_secret`    VARCHAR(255) DEFAULT '',
  ADD COLUMN IF NOT EXISTS `paypal_webhook_id`       VARCHAR(255) DEFAULT '';

-- Carousel settings (v8)
ALTER TABLE `settings`
  ADD COLUMN IF NOT EXISTS `carousel_autoplay`      TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `carousel_interval`      INT        DEFAULT 6000,
  ADD COLUMN IF NOT EXISTS `carousel_pause_hover`   TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `carousel_show_arrows`   TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `carousel_show_dots`     TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `carousel_show_counter`  TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `carousel_transition`    ENUM('fade','slide','zoom') DEFAULT 'fade',
  ADD COLUMN IF NOT EXISTS `carousel_video_audio`   TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `carousel_show_text`     TINYINT(1) DEFAULT 1;

-- v11: Google-Translate language list (admin-configurable, comma CSV, default en,hi,fi)
ALTER TABLE `settings`
  ADD COLUMN IF NOT EXISTS `site_languages` VARCHAR(255) DEFAULT 'en,hi,fi';

UPDATE `settings` SET `site_languages`='en,hi,fi' WHERE `site_languages` IS NULL OR `site_languages`='';

-- Story / Vision / Motto (v9)
ALTER TABLE `settings`
  ADD COLUMN IF NOT EXISTS `story_intro`            TEXT NULL,
  ADD COLUMN IF NOT EXISTS `story_intro_hi`         TEXT NULL,
  ADD COLUMN IF NOT EXISTS `vision_statement`       TEXT NULL,
  ADD COLUMN IF NOT EXISTS `vision_statement_hi`    TEXT NULL,
  ADD COLUMN IF NOT EXISTS `guiding_scripture`      TEXT NULL,
  ADD COLUMN IF NOT EXISTS `scripture_reference`    VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS `motto`                  VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS `motto_hi`               VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS `locations_served`       VARCHAR(255) NULL;

-- Seed settings row (only on first install)
INSERT INTO `settings` (`id`,`site_title`,`tagline`,`email_in`,`email_uk`,`phone_in`,`phone_uk`,`address_in`,`address_uk`,`about_short`,`mission`,`vision`,`values_text`,`default_language`,`gateway_mode`)
SELECT 1,'Sharan Foundation','Hope • Care • Transformation',
       'india@sharanforall.org','uk@sharanforall.org',
       '+91 98765 43210','+44 20 1234 5678',
       'Sharan Foundation Campus, Sangvi, Pune, India',
       'Sharan Foundation UK, London, United Kingdom',
       'A Christian charitable organization committed to uplifting the underprivileged through education, shelter and faith — serving across India & Nepal.',
       'To transform lives by providing education, shelter, and spiritual nurture to underprivileged children, women, and elderly across India and Nepal.',
       'A world where every child is educated, every woman is empowered, every elder is honored, and no one walks alone.',
       'Faith, Compassion, Integrity, Service, and Dignity — the pillars guiding every program and partnership.',
       'en','sandbox'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE id = 1);

-- Populate story content (only if currently NULL/empty)
UPDATE `settings` SET
  `story_intro` = COALESCE(NULLIF(`story_intro`,''),
    'What began as a small group with a heart to serve has grown into a mission dedicated to transforming lives across India and Nepal. Currently, we operate a hostel that provides care, shelter, and support for approximately 50 beneficiaries — including boys, girls, widows, and children from single-parent families.'),
  `vision_statement` = COALESCE(NULLIF(`vision_statement`,''),
    'Transforming lives through Compassion, Care, Education, Empowerment, and Faith — creating a place of hope for the vulnerable and a model of community transformation for generations to come.'),
  `guiding_scripture` = COALESCE(NULLIF(`guiding_scripture`,''),
    'He defends the cause of the fatherless and the widow, and loves the stranger, giving them food and clothing.'),
  `scripture_reference` = COALESCE(NULLIF(`scripture_reference`,''), 'Deuteronomy 10:18'),
  `motto`            = COALESCE(NULLIF(`motto`,''),            'Educate • Equip • Empower • Transform'),
  `motto_hi`         = COALESCE(NULLIF(`motto_hi`,''),         'शिक्षित करें • सुसज्जित करें • सशक्त बनाएँ • रूपांतरित करें'),
  `locations_served` = COALESCE(NULLIF(`locations_served`,''), 'India & Nepal')
WHERE id = 1;

-- ============================================================
-- PROGRAMS (with bilingual columns)
-- ============================================================
CREATE TABLE IF NOT EXISTS `programs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL UNIQUE,
  `subtitle` VARCHAR(255),
  `icon` VARCHAR(20),
  `short_desc` TEXT,
  `long_desc` LONGTEXT,
  `features` TEXT,
  `stats` TEXT,
  `image` VARCHAR(255),
  `display_order` INT DEFAULT 0,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE `programs`
  ADD COLUMN IF NOT EXISTS `title_hi`      VARCHAR(200) NULL,
  ADD COLUMN IF NOT EXISTS `subtitle_hi`   VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS `short_desc_hi` TEXT NULL,
  ADD COLUMN IF NOT EXISTS `long_desc_hi`  LONGTEXT NULL;

INSERT IGNORE INTO `programs` (`title`,`slug`,`subtitle`,`icon`,`short_desc`,`long_desc`,`features`,`stats`,`image`,`display_order`,`title_hi`,`subtitle_hi`,`short_desc_hi`) VALUES
('Child Education','child-education','Education for All','📚','Quality schooling, books, uniforms and tuition for underprivileged children.','We believe every child deserves a quality education — regardless of background. Our schools and learning centers provide free or subsidized education to children from slums, villages and broken homes.','Free tuition & books|School uniforms|Nutritious mid-day meals|Health check-ups|Holistic mentoring|Bible & moral classes','100+:Children Educated|12:Learning Centers|98%:Pass Rate','uploads/programs/child-edu.jpg',1,'बाल शिक्षा','सबके लिए शिक्षा','वंचित बच्चों के लिए गुणवत्तापूर्ण स्कूली शिक्षा।'),
('Girl Child Education','girl-education','Empowering Daughters','👧','Scholarships & mentorship ensuring every girl receives the education she deserves.','In many parts of India, girls are still denied education due to poverty, early marriage or social bias. We break these barriers through dedicated scholarships and mentorship.','Full scholarship support|Career mentorship|STEM & arts exposure|Self-defense training|Menstrual hygiene support|Life-skills coaching','650+:Girls Sponsored|85:College Graduates|0:Drop-Outs in 2024','uploads/programs/girl-edu.jpg',2,'बालिका शिक्षा','बेटियों का सशक्तिकरण','बालिकाओं के लिए छात्रवृत्ति और मार्गदर्शन।'),
('Women Empowerment','women','Skills • Strength • Dignity','💪','Vocational training, micro-enterprise support and life-skill workshops.','We equip women — especially widows and single mothers — with vocational skills, micro-enterprise support and emotional healing.','Tailoring & embroidery|Computer & digital literacy|Beauty & wellness training|Micro-finance support|Counseling & healing|Leadership development','400+:Women Trained|120:Self-Employed|15:SHGs Formed','uploads/programs/women.jpg',3,'महिला सशक्तिकरण','कौशल • शक्ति • गरिमा','महिलाओं के लिए व्यावसायिक प्रशिक्षण।'),
('Old Age Home','oldage','Dignity in Twilight Years','🏡','A loving home, medical care and dignified companionship for the elderly.','For elderly people abandoned by family or society, we provide a home filled with love, medical care and companionship.','Comfortable accommodation|Nutritious meals 3x daily|Medical & nursing care|Spiritual fellowship|Recreational activities|Hospice end-of-life care','60:Current Residents|24/7:Medical Care|100%:Free of Cost','uploads/programs/oldage.jpg',4,'वृद्धाश्रम','ढलती उम्र में गरिमा','बुजुर्गों के लिए प्रेमपूर्ण घर।'),
('Shelter for Single Women','shelter','Safe Haven of Hope','🛡️','A safe haven providing protection, counseling and rehabilitation.','A refuge for women fleeing abuse, abandonment or trafficking. We provide protection, rehabilitation, legal aid, and skills training.','Safe accommodation|Trauma counseling|Legal aid & support|Vocational training|Childcare for kids|Reintegration support','180+:Women Rescued|95%:Rehabilitated|50:Reunited Families','uploads/programs/shelter.jpg',5,'अकेली महिलाओं हेतु आश्रय','आशा का सुरक्षित ठिकाना','सुरक्षित स्थान।'),
('Girls'' Hostel','girls-hostel','Safe Space to Study & Grow','🏠','Safe accommodation enabling rural girls to pursue higher studies.','For girls from rural areas pursuing higher studies, we offer a safe, supportive home away from home.','Safe accommodation|3 meals daily|Wi-Fi & study room|Tuition assistance|Mentorship & counseling|Recreation & sports','50:Current Residents|2:Dormitories|100%:Pass Rate','uploads/programs/girls-hostel.jpg',6,'लड़कियों का छात्रावास','पढ़ने व बढ़ने का सुरक्षित स्थान','उच्च शिक्षा प्राप्त करने वाली लड़कियों के लिए सुरक्षित आवास।'),
('Boys'' Hostel','boys-hostel','Building Men of Character','🎓','Affordable accommodation, mentoring and character building for boys.','Affordable accommodation, mentoring and discipline-based growth for young men pursuing education.','Affordable lodging|Nutritious meals|Study halls & Wi-Fi|Mentorship program|Spiritual formation|Leadership training','95:Current Residents|Continued:Operation|50+:College Graduates','',7,'लड़कों का छात्रावास','चरित्रवान युवा निर्माण','लड़कों के लिए किफायती आवास।'),
('Acts Bible College','bible-college','Equipping Servants of God','✝️','Theological training that equips men and women to serve God and society.','Theological training equipping believers, church leaders, and ministry workers with sound biblical knowledge and practical ministry training.','Diploma in Theology|Bachelor of Divinity|Certificate in Ministry|On-campus boarding|Field-based internship|Faculty mentorship','80+:Current Students|200+:Alumni Serving|3:Diploma Programs','',8,'एक्ट्स बाइबल कॉलेज','परमेश्वर के सेवकों को तैयार करना','धार्मिक प्रशिक्षण।');

-- ============================================================
-- PROJECTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `category` ENUM('education','shelter','infrastructure','outreach') DEFAULT 'education',
  `description` TEXT,
  `location` VARCHAR(150),
  `goal_amount` DECIMAL(12,2) DEFAULT 0,
  `raised_amount` DECIMAL(12,2) DEFAULT 0,
  `currency` ENUM('INR','GBP','USD') DEFAULT 'INR',
  `status` ENUM('active','completed','urgent','seasonal') DEFAULT 'active',
  `days_left` INT DEFAULT 0,
  `image` VARCHAR(255),
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE `projects`
  ADD COLUMN IF NOT EXISTS `title_hi`       VARCHAR(200) NULL,
  ADD COLUMN IF NOT EXISTS `description_hi` TEXT NULL;

INSERT IGNORE INTO `projects` (`id`,`title`,`category`,`description`,`location`,`goal_amount`,`raised_amount`,`currency`,`status`,`days_left`,`image`) VALUES
(1,'New Girls'' Hostel Block','infrastructure','Building a new hostel block to accommodate more rural girls.','Pune, India',3000000,1850000,'INR','active',28,'uploads/projects/girls-hostel.jpg'),
(2,'Sponsor 500 Children for 2026','education','Annual sponsorship covering school fees, books, uniforms and meals.','India & Nepal',75000,42000,'GBP','active',0,'uploads/projects/child-edu.jpg'),
(3,'Expand Women''s Shelter Home','shelter','Renovate and expand the women''s shelter for more rescued women.','India',2000000,680000,'INR','urgent',0,'uploads/projects/shelter.jpg'),
(4,'Medical Wing for Old Age Home','infrastructure','Establish a medical wing with nursing care for elderly residents.','Pune, India',1500000,1120000,'INR','active',45,'uploads/projects/oldage.jpg'),
(5,'Bible College Library Expansion','education','Add theological books, e-resources and digital workstations.','Bible College Campus',20000,8500,'GBP','active',0,''),
(6,'Village Skills Outreach','outreach','Mobile vocational training caravan reaching villages.','Rural India',800000,450000,'INR','active',0,'uploads/projects/women.jpg'),
(7,'Christmas Joy Boxes 2026','outreach','Distribute gift boxes with clothes, toys, food & Bibles.','India & Nepal',12000,3200,'GBP','seasonal',0,''),
(8,'Children''s Dormitory Renovation','shelter','Fully renovated dormitory with new beds, fans, lighting and desks.','Pune, India',1200000,1200000,'INR','completed',0,'uploads/projects/girl-edu.jpg');

-- ============================================================
-- BLOG POSTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `category` VARCHAR(100),
  `excerpt` TEXT,
  `content` LONGTEXT,
  `image` VARCHAR(255),
  `author` VARCHAR(100) DEFAULT 'Admin',
  `read_time` VARCHAR(20) DEFAULT '5 min read',
  `tags` VARCHAR(500),
  `is_featured` TINYINT(1) DEFAULT 0,
  `status` ENUM('published','draft') DEFAULT 'published',
  `published_at` DATE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE `blog_posts`
  ADD COLUMN IF NOT EXISTS `title_hi`   VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS `excerpt_hi` TEXT NULL,
  ADD COLUMN IF NOT EXISTS `content_hi` LONGTEXT NULL;

INSERT IGNORE INTO `blog_posts` (`id`,`title`,`slug`,`category`,`excerpt`,`content`,`image`,`author`,`read_time`,`tags`,`is_featured`,`published_at`) VALUES
(1,'From Slum to Scholarship: Priya''s Inspiring Journey','priya-slum-to-scholarship','Success Stories','When 8-year-old Priya first walked into our learning center, she could barely hold a pencil. Today she is a registered nurse serving her community.','<p><strong>When 8-year-old Priya first walked into our learning center, she could barely hold a pencil.</strong> Today she is a registered nurse serving in a community health clinic.</p><h2>A Humble Beginning</h2><p>Within three years, she had topped her class.</p>','uploads/blog/girl-edu.jpg','Mary John','8 min read','Success Story,Education,Sponsorship,Hope',1,'2026-05-28'),
(2,'How Vocational Training Changed Lakshmi''s Life','lakshmi-vocational-training','Women','A widowed mother of three, Lakshmi found hope through our tailoring program.','<p>Today she runs a thriving tailoring boutique that employs 4 other women.</p>','uploads/blog/women.jpg','Esther George','5 min read','Women,Empowerment,Story',0,'2026-05-20'),
(3,'Celebrating 100 Years: Stories from Our Old Age Home','100-years-stories','Old Age','Mr. Joseph, our oldest resident, turned 100 this month.','<p>His stories of faith, perseverance and joy moved everyone.</p>','uploads/blog/oldage.jpg','Daniel Abraham','4 min read','Old Age,Celebration',0,'2026-05-12'),
(4,'Bible College Graduation: 22 Servants Sent Out','bible-college-graduation-2026','Bible College','22 graduates from Acts Bible College were commissioned this year.','<p>Their hearts are set on serving the unreached.</p>','','Pastor Samuel Kumar','6 min read','Bible College,Graduation,Ministry',0,'2026-05-05');

-- ============================================================
-- GALLERY
-- ============================================================
CREATE TABLE IF NOT EXISTS `gallery` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `caption` VARCHAR(255),
  `category` ENUM('education','women','oldage','hostel','bible','events') DEFAULT 'events',
  `image` VARCHAR(255) NOT NULL,
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE `gallery`
  ADD COLUMN IF NOT EXISTS `title_hi`   VARCHAR(200) NULL,
  ADD COLUMN IF NOT EXISTS `caption_hi` VARCHAR(255) NULL;

INSERT IGNORE INTO `gallery` (`id`,`title`,`caption`,`category`,`image`) VALUES
(1,'Joy of Learning','Children at our learning center','education','uploads/gallery/child-edu.jpg'),
(2,'Dreams Take Flight','A sponsored girl pursuing her dreams','education','uploads/gallery/girl-edu.jpg'),
(3,'Skills That Empower','Women learning tailoring skills','women','uploads/gallery/women.jpg'),
(4,'Honored & Loved','Caring for our elders with love','oldage','uploads/gallery/oldage.jpg'),
(5,'Safe Haven','A safe haven for rescued women','women','uploads/gallery/shelter.jpg'),
(6,'Home Away From Home','Girls'' hostel — a home away from home','hostel','uploads/gallery/girls-hostel.jpg');

-- ============================================================
-- TEAM MEMBERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `team_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `role` VARCHAR(150),
  `bio` TEXT,
  `image` VARCHAR(255),
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE `team_members`
  ADD COLUMN IF NOT EXISTS `name_hi` VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS `role_hi` VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS `bio_hi`  TEXT NULL;

INSERT IGNORE INTO `team_members` (`id`,`name`,`role`,`bio`,`display_order`,`name_hi`,`role_hi`) VALUES
(1,'Rev. John David','Founder & President','Vision-bearer of Sharan Foundation, serving full-time since 2012.',1,'रेव. जॉन डेविड','संस्थापक एवं अध्यक्ष'),
(2,'Mary John','Co-Founder & Director','Leads women & children programs across India.',2,'मैरी जॉन','सह-संस्थापक एवं निदेशक'),
(3,'Pastor Samuel Kumar','Principal, Bible College','Heads theological training & discipleship ministry.',3,'पास्टर सैमुअल कुमार','प्राचार्य, बाइबल कॉलेज'),
(4,'Daniel Abraham','Operations Manager','Oversees campus operations and hostel management.',4,'डेनियल अब्राहम','संचालन प्रबंधक'),
(5,'Esther George','Women Empowerment Lead','Mentors and trains women in vocational skills.',5,'एस्तेर जॉर्ज','महिला सशक्तिकरण प्रमुख');

-- ============================================================
-- TESTIMONIALS
-- ============================================================
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `role` VARCHAR(150),
  `message` TEXT NOT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE `testimonials`
  ADD COLUMN IF NOT EXISTS `name_hi`    VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS `role_hi`    VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS `message_hi` TEXT NULL;

INSERT IGNORE INTO `testimonials` (`id`,`name`,`role`,`message`,`message_hi`,`role_hi`) VALUES
(1,'Priya R.','Beneficiary, India','Sharan Foundation gave me the chance to study when my family couldn''t afford it. Today I''m a nurse serving my community.','शरण फाउंडेशन ने मुझे पढ़ाई का अवसर दिया।','लाभार्थी, भारत'),
(2,'Sarah M.','Resident, Shelter Home','After losing my husband, I had nowhere to go. The shelter became my home and gave me dignity.','आश्रय मेरा घर बन गया।','निवासी, आश्रय गृह'),
(3,'James T.','UK Partner & Donor','Partnering with Sharan Foundation has been one of the most meaningful journeys of our lives.','यह हमारे जीवन की सबसे सार्थक यात्राओं में से एक रही है।','यूके भागीदार और दानदाता');

-- ============================================================
-- VOLUNTEERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `volunteers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `country` VARCHAR(100),
  `city` VARCHAR(100),
  `age` INT,
  `gender` ENUM('Male','Female','Other','Prefer not to say'),
  `occupation` VARCHAR(150),
  `area_of_interest` VARCHAR(255),
  `availability` VARCHAR(100),
  `skills` TEXT,
  `experience` TEXT,
  `motivation` TEXT,
  `status` ENUM('new','reviewed','approved','rejected') DEFAULT 'new',
  `admin_notes` TEXT,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- PARTNERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `partners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `org_name` VARCHAR(200) NOT NULL,
  `org_type` ENUM('Corporate','Church','NGO','Foundation','Individual','Other') DEFAULT 'Corporate',
  `contact_person` VARCHAR(150) NOT NULL,
  `designation` VARCHAR(150),
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `country` VARCHAR(100),
  `website` VARCHAR(255),
  `partnership_type` VARCHAR(255),
  `budget_range` VARCHAR(100),
  `programs_of_interest` TEXT,
  `proposal` TEXT,
  `status` ENUM('new','reviewed','approved','rejected') DEFAULT 'new',
  `admin_notes` TEXT,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- CONTACT MESSAGES
-- ============================================================
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50),
  `interest` VARCHAR(100),
  `office` VARCHAR(50),
  `message` TEXT NOT NULL,
  `status` ENUM('new','read','replied') DEFAULT 'new',
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- NEWSLETTER SUBSCRIBERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `subscribers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('active','unsubscribed') DEFAULT 'active'
) ENGINE=InnoDB;

-- ============================================================
-- DONATIONS (v3)
-- ============================================================
CREATE TABLE IF NOT EXISTS `donations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `donor_name`      VARCHAR(150) NOT NULL,
  `email`           VARCHAR(150) NOT NULL,
  `phone`           VARCHAR(50)  NOT NULL,
  `address`         TEXT,
  `city`            VARCHAR(100),
  `state`           VARCHAR(100),
  `country`         VARCHAR(100) DEFAULT 'India',
  `pincode`         VARCHAR(20),
  `pan_number`      VARCHAR(20),
  `amount`          DECIMAL(12,2) NOT NULL,
  `currency`        ENUM('INR','GBP','USD') DEFAULT 'INR',
  `donation_type`   ENUM('one-time','monthly','yearly') DEFAULT 'one-time',
  `purpose`         VARCHAR(100),
  `project_id`      INT NULL,
  `message`         TEXT,
  `payment_method`  ENUM('upi','razorpay','stripe','paypal','bank_transfer','cheque','cash','other') DEFAULT 'bank_transfer',
  `payment_status`  ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
  `transaction_id`  VARCHAR(150) NULL,
  `payment_date`    DATE NULL,
  `is_anonymous`    TINYINT(1) DEFAULT 0,
  `receipt_required`TINYINT(1) DEFAULT 1,
  `newsletter_optin`TINYINT(1) DEFAULT 0,
  `admin_notes`     TEXT,
  `receipt_number`  VARCHAR(50) NULL,
  `receipt_sent_at` TIMESTAMP NULL,
  `submitted_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (`payment_status`),
  INDEX idx_email  (`email`),
  INDEX idx_date   (`submitted_at`)
) ENGINE=InnoDB;

-- v6: payment gateway tracking columns on donations
ALTER TABLE `donations`
  ADD COLUMN IF NOT EXISTS `gateway`              VARCHAR(50)  NULL,
  ADD COLUMN IF NOT EXISTS `gateway_order_id`     VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS `gateway_payment_id`   VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS `gateway_signature`    VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS `gateway_response_raw` TEXT         NULL,
  ADD COLUMN IF NOT EXISTS `gateway_fee`          DECIMAL(12,2) DEFAULT 0;

INSERT IGNORE INTO `donations` (`id`,`donor_name`,`email`,`phone`,`country`,`amount`,`currency`,`donation_type`,`purpose`,`payment_method`,`payment_status`,`payment_date`,`receipt_number`) VALUES
(1,'Rahul Sharma','rahul@example.com','+91 98765 11111','India',5000,'INR','one-time','Child Education','upi','completed','2026-06-01','AF-2026-001'),
(2,'Sarah Williams','sarah@example.co.uk','+44 20 1111 2222','United Kingdom',100,'GBP','monthly','Women Empowerment','stripe','completed','2026-06-02','AF-2026-002'),
(3,'Priya Reddy','priya@example.com','+91 91234 55555','India',2500,'INR','one-time','Old Age Home','bank_transfer','pending',NULL,NULL),
(4,'David Kumar','david@example.com','+91 99887 33333','India',10000,'INR','yearly','Bible College','razorpay','completed','2026-05-28','AF-2026-003'),
(5,'John Smith','john@example.com','+44 20 9999 8888','United Kingdom',25,'GBP','one-time','Where Most Needed','paypal','completed','2026-05-25','AF-2026-004');

-- ============================================================
-- FUNDRAISERS (v4)
-- ============================================================
CREATE TABLE IF NOT EXISTS `fundraisers` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `organizer_name`  VARCHAR(150) NOT NULL,
  `organizer_email` VARCHAR(150) NOT NULL,
  `organizer_phone` VARCHAR(50),
  `organizer_bio`   TEXT,
  `title`           VARCHAR(200) NOT NULL,
  `slug`            VARCHAR(200) UNIQUE,
  `cause`           VARCHAR(150) DEFAULT 'Where Most Needed',
  `story`           LONGTEXT,
  `cover_image`     VARCHAR(255),
  `goal_amount`     DECIMAL(12,2) NOT NULL,
  `currency`        ENUM('INR','GBP','USD') DEFAULT 'INR',
  `raised_amount`   DECIMAL(12,2) DEFAULT 0,
  `start_date`      DATE,
  `end_date`        DATE,
  `status`          ENUM('pending','active','completed','rejected','closed') DEFAULT 'pending',
  `is_featured`     TINYINT(1) DEFAULT 0,
  `admin_notes`     TEXT,
  `submitted_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (`status`),
  INDEX idx_slug   (`slug`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `fundraiser_contributions` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `fundraiser_id` INT NOT NULL,
  `donor_name`    VARCHAR(150) NOT NULL,
  `email`         VARCHAR(150),
  `amount`        DECIMAL(12,2) NOT NULL,
  `currency`      ENUM('INR','GBP','USD') DEFAULT 'INR',
  `message`       TEXT,
  `is_anonymous`  TINYINT(1) DEFAULT 0,
  `payment_status`ENUM('pending','completed','failed') DEFAULT 'pending',
  `contributed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fundraiser (`fundraiser_id`),
  FOREIGN KEY (`fundraiser_id`) REFERENCES `fundraisers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO `fundraisers` (`id`,`organizer_name`,`organizer_email`,`organizer_phone`,`organizer_bio`,`title`,`slug`,`cause`,`story`,`goal_amount`,`currency`,`raised_amount`,`start_date`,`end_date`,`status`,`is_featured`) VALUES
(1,'Priya Sharma','priya.fundraiser@example.com','+91 98765 11111','Software engineer turning 30 this year.','My 30th Birthday Fundraiser for Girls'' Education','priya-30th-birthday-girls','Girl Child Education','Instead of birthday gifts, I want to fund education for 10 rural girls.',50000,'INR',32500,'2026-05-01','2026-06-30','active',1),
(2,'Michael Brown','michael.brown@example.co.uk','+44 20 7777 2222','Marathon runner & father of two','London Marathon Run for Sharan Foundation','marathon-london-acts','Old Age Home','I''m running the London Marathon to raise funds.',5000,'GBP',1850,'2026-06-01','2026-10-15','active',1),
(3,'John Mathew','john.m@example.com','+91 98765 33333','Pastor and missionary','Help Build New Classroom — Pune','build-new-classroom','Child Education','We need more classrooms for our growing center.',300000,'INR',125000,'2026-05-15','2026-08-31','active',0);

-- ============================================================
-- RECURRING DONATIONS (v5)
-- ============================================================
CREATE TABLE IF NOT EXISTS `recurring_donations` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `donor_name`        VARCHAR(150) NOT NULL,
  `email`             VARCHAR(150) NOT NULL,
  `phone`             VARCHAR(50),
  `country`           VARCHAR(100),
  `pan_number`        VARCHAR(20),
  `amount`            DECIMAL(12,2) NOT NULL,
  `currency`          ENUM('INR','GBP','USD') DEFAULT 'INR',
  `frequency`         ENUM('weekly','monthly','quarterly','yearly') DEFAULT 'monthly',
  `purpose`           VARCHAR(100) DEFAULT 'Where Most Needed',
  `payment_method`    ENUM('upi','razorpay','stripe','paypal','bank_transfer','standing_order','other') DEFAULT 'bank_transfer',
  `payment_token`     VARCHAR(255) NULL,
  `status`            ENUM('active','paused','cancelled','expired') DEFAULT 'active',
  `start_date`        DATE NOT NULL,
  `next_charge_date`  DATE NOT NULL,
  `last_charged_at`   TIMESTAMP NULL,
  `last_charge_status`VARCHAR(50) NULL,
  `total_cycles`      INT DEFAULT 0,
  `total_raised`      DECIMAL(12,2) DEFAULT 0,
  `failed_attempts`   INT DEFAULT 0,
  `max_cycles`        INT NULL,
  `manage_token`      VARCHAR(64) NOT NULL UNIQUE,
  `cancelled_at`      TIMESTAMP NULL,
  `cancellation_reason` TEXT,
  `created_donation_id` INT NULL,
  `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status_date (`status`, `next_charge_date`),
  INDEX idx_email       (`email`),
  INDEX idx_token       (`manage_token`)
) ENGINE=InnoDB;

-- v6: gateway subscription columns
ALTER TABLE `recurring_donations`
  ADD COLUMN IF NOT EXISTS `gateway`                 VARCHAR(50)  NULL,
  ADD COLUMN IF NOT EXISTS `gateway_customer_id`     VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS `gateway_subscription_id` VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS `gateway_plan_id`         VARCHAR(150) NULL;

CREATE TABLE IF NOT EXISTS `recurring_charges` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `recurring_id`        INT NOT NULL,
  `donation_id`         INT NULL,
  `amount`              DECIMAL(12,2) NOT NULL,
  `currency`            ENUM('INR','GBP','USD') DEFAULT 'INR',
  `status`              ENUM('success','failed','retry','manual','reminder_sent') NOT NULL,
  `gateway_response`    TEXT,
  `notes`               TEXT,
  `charged_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recurring (`recurring_id`),
  FOREIGN KEY (`recurring_id`) REFERENCES `recurring_donations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cron_log` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `job_name`       VARCHAR(100) NOT NULL,
  `processed`      INT DEFAULT 0,
  `succeeded`      INT DEFAULT 0,
  `failed`         INT DEFAULT 0,
  `reminders_sent` INT DEFAULT 0,
  `details`        TEXT,
  `duration_ms`    INT DEFAULT 0,
  `ran_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO `recurring_donations`
(`id`,`donor_name`,`email`,`phone`,`country`,`amount`,`currency`,`frequency`,`purpose`,`payment_method`,`status`,`start_date`,`next_charge_date`,`manage_token`) VALUES
(1,'Sarah Williams','sarah@example.co.uk','+44 20 1111 2222','United Kingdom',25,'GBP','monthly','Child Education','stripe','active','2025-12-02', DATE_ADD(CURDATE(), INTERVAL 3 DAY), MD5(CONCAT('sarah-seed', RAND()))),
(2,'David Kumar','david@example.com','+91 99887 33333','India',1000,'INR','monthly','Bible College','upi','active','2026-01-15', DATE_ADD(CURDATE(), INTERVAL 1 DAY), MD5(CONCAT('david-seed', RAND()))),
(3,'Esther Joseph','esther@example.com','+91 98123 45678','India',500,'INR','weekly','Women Empowerment','razorpay','active','2026-04-01', CURDATE(), MD5(CONCAT('esther-seed', RAND())));

-- ============================================================
-- WEBHOOK EVENTS (v6)
-- ============================================================
CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `gateway`       VARCHAR(50) NOT NULL,
  `event_type`    VARCHAR(100) NOT NULL,
  `event_id`      VARCHAR(150) NULL UNIQUE,
  `payload`       LONGTEXT,
  `signature_ok`  TINYINT(1) DEFAULT 0,
  `processed`     TINYINT(1) DEFAULT 0,
  `result`        TEXT,
  `donation_id`   INT NULL,
  `received_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_gw_type (`gateway`, `event_type`),
  INDEX idx_event   (`event_id`)
) ENGINE=InnoDB;

-- ============================================================
-- HERO SLIDES (v7 + v8 video support)
-- ============================================================
CREATE TABLE IF NOT EXISTS `hero_slides` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `title`           VARCHAR(200) NOT NULL,
  `title_hi`        VARCHAR(200) NULL,
  `subtitle`        VARCHAR(255) NULL,
  `subtitle_hi`     VARCHAR(255) NULL,
  `description`     TEXT NULL,
  `description_hi`  TEXT NULL,
  `image`           VARCHAR(255) NOT NULL,
  `cta_text`        VARCHAR(80) DEFAULT 'Learn More',
  `cta_text_hi`     VARCHAR(80) NULL,
  `cta_link`        VARCHAR(255) DEFAULT '#',
  `cta_text_2`      VARCHAR(80) NULL,
  `cta_text_2_hi`   VARCHAR(80) NULL,
  `cta_link_2`      VARCHAR(255) NULL,
  `overlay_color`   VARCHAR(20) DEFAULT 'blue',
  `text_position`   ENUM('left','center','right') DEFAULT 'left',
  `badge_text`      VARCHAR(100) NULL,
  `display_order`   INT DEFAULT 0,
  `status`          ENUM('active','inactive') DEFAULT 'active',
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_active (`status`,`display_order`)
) ENGINE=InnoDB;

-- v8: video background columns
ALTER TABLE `hero_slides`
  ADD COLUMN IF NOT EXISTS `media_type`   ENUM('image','video','youtube','vimeo') DEFAULT 'image',
  ADD COLUMN IF NOT EXISTS `video_file`   VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS `video_url`    VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS `poster_image` VARCHAR(255) NULL;

-- v10: per-slide "show text over image" toggle (admin control)
ALTER TABLE `hero_slides`
  ADD COLUMN IF NOT EXISTS `show_text` TINYINT(1) DEFAULT 1;

INSERT IGNORE INTO `hero_slides`
(`id`,`title`,`title_hi`,`subtitle`,`subtitle_hi`,`description`,`description_hi`,`image`,`cta_text`,`cta_text_hi`,`cta_link`,`cta_text_2`,`cta_text_2_hi`,`cta_link_2`,`overlay_color`,`badge_text`,`display_order`) VALUES
(1,'Bringing Hope Through Child Education','बाल शिक्षा के माध्यम से आशा लाना','Every Child Deserves a Future','हर बच्चे को एक भविष्य चाहिए','Quality schooling, books, uniforms and meals for underprivileged children across India and Nepal.','भारत और नेपाल में वंचित बच्चों के लिए गुणवत्तापूर्ण शिक्षा।','uploads/hero/slide-child-education.jpg','Sponsor a Child','एक बच्चे को प्रायोजित करें','pages/donate.php','Learn More','और जानें','pages/programs.php#child-education','blue','✦ CHILD EDUCATION',1),
(2,'Empowering Every Girl to Dream Big','हर बालिका को बड़े सपने देखने के लिए सशक्त बनाना','Girl Child Education','बालिका शिक्षा','Breaking barriers through scholarships, mentorship and safe learning spaces.','छात्रवृत्ति और मार्गदर्शन।','uploads/hero/slide-girl-education.jpg','Support Girls','लड़कियों का समर्थन करें','pages/donate.php','Read Stories','कहानियाँ पढ़ें','pages/blog.php','blue','✦ GIRL CHILD EDUCATION',2),
(3,'Skills That Build Independence','कौशल जो स्वतंत्रता बनाते हैं','Women Empowerment','महिला सशक्तिकरण','Vocational training, micro-enterprise support and life-skills.','व्यावसायिक प्रशिक्षण।','uploads/hero/slide-women.jpg','Empower a Woman','एक महिला को सशक्त बनाएँ','pages/donate.php','Our Programs','हमारे कार्यक्रम','pages/programs.php#women','amber','✦ WOMEN EMPOWERMENT',3),
(4,'Honoring Our Elders With Love','प्रेम से हमारे बुजुर्गों का सम्मान','Old Age Home','वृद्धाश्रम','A loving home, medical care and dignified companionship for the elderly.','बुजुर्गों के लिए प्रेमपूर्ण घर।','uploads/hero/slide-oldage.jpg','Honor an Elder','बुजुर्ग का सम्मान करें','pages/donate.php','Visit Our Home','हमारा घर देखें','pages/programs.php#oldage','dark','✦ OLD AGE HOME',4),
(5,'A Safe Home Away From Home','घर से दूर एक सुरक्षित घर','Hostels for Boys & Girls','लड़कों और लड़कियों के लिए छात्रावास','Safe accommodation, nutritious meals, mentorship and study support.','सुरक्षित आवास और सहायता।','uploads/hero/slide-hostel.jpg','Fund a Hostel Bed','छात्रावास का बिस्तर प्रायोजित करें','pages/donate.php','Hostels','छात्रावास','pages/programs.php#girls-hostel','blue','✦ HOSTELS',5),
(6,'Equipping Servants of God','परमेश्वर के सेवकों को तैयार करना','Acts Bible College','एक्ट्स बाइबल कॉलेज','Theological training and discipleship preparing the next generation of ministers.','धार्मिक प्रशिक्षण।','uploads/hero/slide-bible.jpg','Support a Student','एक छात्र का समर्थन करें','pages/donate.php','About the College','कॉलेज के बारे में','pages/programs.php#bible-college','dark','✦ BIBLE COLLEGE',6);

-- ============================================================
-- MARQUEE ANNOUNCEMENTS (ticker between hero & stats — v11)
-- Add / edit / delete entries from Admin → Marquee.
-- The ticker is hidden on the homepage automatically when no
-- active marquee rows exist.
-- ============================================================
CREATE TABLE IF NOT EXISTS `marquees` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `text`            VARCHAR(255) NOT NULL,
  `text_hi`         VARCHAR(255) NULL,
  `icon`            VARCHAR(20) NULL,
  `display_order`   INT DEFAULT 0,
  `status`          ENUM('active','inactive') DEFAULT 'active',
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_order (`status`,`display_order`)
) ENGINE=InnoDB;

INSERT IGNORE INTO `marquees` (`id`,`text`,`text_hi`,`icon`,`display_order`,`status`) VALUES
(1,'Supporting 2,500+ children with education, shelter and love across India & Nepal',NULL,'💝',1,'active'),
(2,'Your generosity brings hope — volunteer, partner or donate today',NULL,'🙏',2,'active');

-- ============================================================
-- SITE MEDIA (v11) — page hero banners & content images that the
-- admin can replace from Admin → Banners & Images. Each row maps a
-- fixed usage key (slug) to an uploaded image. path may be reset to
-- the built-in default by the admin panel.
-- ============================================================
CREATE TABLE IF NOT EXISTS `site_media` (
  `slug`          VARCHAR(80) PRIMARY KEY,
  `label`         VARCHAR(150) NOT NULL,
  `group_name`    VARCHAR(80)  NOT NULL DEFAULT 'content',
  `default_path`  VARCHAR(255) NOT NULL,
  `path`          VARCHAR(255) NOT NULL,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO `site_media` (`slug`,`label`,`group_name`,`default_path`,`path`) VALUES
-- Page hero banners
('banner_about','About page banner','banner','images/hero.jpg','images/hero.jpg'),
('banner_blog','Blog page banner','banner','images/hero.jpg','images/hero.jpg'),
('banner_contact','Contact page banner','banner','images/hero.jpg','images/hero.jpg'),
('banner_gallery','Gallery page banner','banner','images/hero.jpg','images/hero.jpg'),
('banner_partner','Partner page banner','banner','images/hero.jpg','images/hero.jpg'),
('banner_programs','Programs page banner','banner','images/hero.jpg','images/hero.jpg'),
('banner_projects','Projects page banner','banner','images/hero.jpg','images/hero.jpg'),
('banner_start_fundraiser','Start Fundraiser page banner','banner','images/hero.jpg','images/hero.jpg'),
('banner_volunteer','Volunteer page banner','banner','images/hero.jpg','images/hero.jpg'),
('banner_donate','Donate page banner','banner','images/donate-bg.jpg','images/donate-bg.jpg'),
-- Homepage & content images
('home_hero','Homepage fallback hero / video-cover image','content','images/hero.jpg','images/hero.jpg'),
('story_img','Homepage & About “Our Story” image','content','images/about.jpg','images/about.jpg'),
('donate_bg','Donation banner / call-to-action background','content','images/donate-bg.jpg','images/donate-bg.jpg');

-- ============================================================
-- MILESTONES (v9)
-- ============================================================
CREATE TABLE IF NOT EXISTS `milestones` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `year`            VARCHAR(20) NOT NULL,
  `title`           VARCHAR(200) NOT NULL,
  `title_hi`        VARCHAR(200) NULL,
  `description`     TEXT,
  `description_hi`  TEXT,
  `icon`            VARCHAR(20) DEFAULT '✦',
  `is_highlight`    TINYINT(1) DEFAULT 0,
  `display_order`   INT DEFAULT 0,
  `status`          ENUM('active','inactive') DEFAULT 'active',
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_order (`display_order`)
) ENGINE=InnoDB;

INSERT IGNORE INTO `milestones` (`id`,`year`,`title`,`description`,`icon`,`is_highlight`,`display_order`) VALUES
(1,'2012','The Beginning','Started in a rented facility in Sangvi, Pune, India. Began with one classroom serving 20 children.','🌱',1,1),
(2,'2015','Growing Impact','Expanded outreach and support services. Beneficiaries grew to 30 children.','🌿',0,2),
(3,'2019','Hostel Development','Established Girls'' Hostel (50 capacity) with two dormitories. Continued operating Boys'' Hostel in rented facility.','🏠',0,3),
(4,'2022','Infrastructure Expansion','Added Reception Area, Study Hall, Conference Room. Total residential capacity reached 50 beneficiaries.','🏗️',0,4),
(5,'2024','Education & Skill Development','Established a Training and Learning Centre with modern equipment, projector, laptops and digital learning resources.','💻',1,5),
(6,'Today','Impacting 1,000+ Lives','Supporting 50 residents and impacting 1,000+ lives through education, care, and skill development across India and Nepal.','🌟',1,6);

-- ============================================================
-- MISSION PHASES (v9)
-- ============================================================
CREATE TABLE IF NOT EXISTS `mission_phases` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `phase_number`    INT NOT NULL,
  `title`           VARCHAR(200) NOT NULL,
  `title_hi`        VARCHAR(200) NULL,
  `description`     TEXT,
  `description_hi`  TEXT,
  `capacity`        VARCHAR(100) NULL,
  `icon`            VARCHAR(20) DEFAULT '🎯',
  `status`          ENUM('upcoming','active','completed') DEFAULT 'upcoming',
  `display_order`   INT DEFAULT 0,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO `mission_phases` (`id`,`phase_number`,`title`,`description`,`capacity`,`icon`,`status`,`display_order`) VALUES
(1,1,'Land Acquisition','Purchase land and develop a sustainable, self-sufficient care campus.','15–20 acres','🏞️','active',1),
(2,2,'Old Age Home','Safe, dignified, and compassionate elder care.','500 residents','🏡','upcoming',2),
(3,3,'Orphanage & Child Care Centre','Education, protection, and holistic development.','500 youth + 200 children','🧒','upcoming',3),
(4,4,'Women Care Centre','Support for widows, single mothers, and women in crisis.','300 women','💪','upcoming',4),
(5,5,'Medical & Wellness Zone','Primary healthcare, preventive care, counseling and wellness services.','Full medical wing','🏥','upcoming',5),
(6,6,'Education & Skill Development Centre','Formal education, vocational training, employment readiness.','Multi-program','🎓','upcoming',6);

-- ============================================================
-- VISION CAPACITY (v9)
-- ============================================================
CREATE TABLE IF NOT EXISTS `vision_capacity` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `category`        VARCHAR(150) NOT NULL,
  `category_hi`     VARCHAR(150) NULL,
  `capacity`        INT NOT NULL,
  `icon`            VARCHAR(20) DEFAULT '👥',
  `display_order`   INT DEFAULT 0,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO `vision_capacity` (`id`,`category`,`capacity`,`icon`,`display_order`) VALUES
(1,'Old Age Home',                  500, '🏡', 1),
(2,'Women Care Centre',             300, '💪', 2),
(3,'Orphan & Child Care',           200, '🧒', 3),
(4,'Youth Development Centre',      500, '🎓', 4);

-- ============================================================
-- PROGRAM COURSES (v9)
-- ============================================================
CREATE TABLE IF NOT EXISTS `program_courses` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `program_id`      INT NULL,
  `category`        VARCHAR(150) NOT NULL,
  `category_hi`     VARCHAR(150) NULL,
  `course_name`     VARCHAR(200) NOT NULL,
  `course_name_hi`  VARCHAR(200) NULL,
  `description`     TEXT,
  `description_hi`  TEXT,
  `icon`            VARCHAR(20) DEFAULT '📚',
  `display_order`   INT DEFAULT 0,
  `status`          ENUM('active','inactive') DEFAULT 'active',
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cat (`category`,`display_order`)
) ENGINE=InnoDB;

INSERT IGNORE INTO `program_courses` (`id`,`category`,`course_name`,`icon`,`display_order`) VALUES
-- Media & Communication
(1,'Media & Communication','Photography','📷',1),
(2,'Media & Communication','Videography','🎥',2),
(3,'Media & Communication','Graphic Design','🎨',3),
(4,'Media & Communication','Content Creation','✍️',4),
(5,'Media & Communication','Social Media Skills','📱',5),
-- Public Address (PA) System
(6,'Public Address (PA) System Training','Sound System Operations','🔊',1),
(7,'Public Address (PA) System Training','Audio Mixing','🎚️',2),
(8,'Public Address (PA) System Training','Event Sound Management','🎤',3),
(9,'Public Address (PA) System Training','Technical Setup & Maintenance','🛠️',4),
-- Music
(10,'Music Learning Program','Vocal Training','🎙️',1),
(11,'Music Learning Program','Keyboard','🎹',2),
(12,'Music Learning Program','Guitar','🎸',3),
(13,'Music Learning Program','Worship Music Training','🎵',4),
(14,'Music Learning Program','Music Theory Basics','📖',5),
-- Stitching & Tailoring
(15,'Stitching & Tailoring','Basic Sewing','🧵',1),
(16,'Stitching & Tailoring','Garment Making','👗',2),
(17,'Stitching & Tailoring','Alterations','✂️',3),
(18,'Stitching & Tailoring','Fashion & Design Basics','👔',4),
(19,'Stitching & Tailoring','Entrepreneurship Skills','💼',5),
-- Cooking & Baking
(20,'Cooking & Baking','Food Preparation','🍲',1),
(21,'Cooking & Baking','Bakery Products','🥐',2),
(22,'Cooking & Baking','Hygiene & Food Safety','🧼',3),
(23,'Cooking & Baking','Catering Skills','🍽️',4),
(24,'Cooking & Baking','Small Business Development','💵',5),
-- Bible College
(25,'ACTS Bible College','Certificate in Biblical Studies','📜',1),
(26,'ACTS Bible College','Diploma in Theology','📖',2),
(27,'ACTS Bible College','Leadership Development','👑',3),
(28,'ACTS Bible College','Ministry Training','⛪',4),
(29,'ACTS Bible College','Missions & Evangelism','🌍',5),
(30,'ACTS Bible College','Church Planting and Discipleship','🌱',6);

-- ============================================================
-- RECORD SCHEMA VERSION (only inserts if this version not already logged)
-- ============================================================
INSERT INTO `schema_version` (`version`, `description`, `applied_by`, `notes`)
SELECT 'v1.0.0', 'Consolidated single-file install — all features (v1–v9)', 'installer',
       'Includes: core tables, donations, fundraisers, recurring, payment gateways, hero carousel with video, story content (milestones, mission phases, courses), multi-language support'
WHERE NOT EXISTS (SELECT 1 FROM `schema_version` WHERE `version` = 'v1.0.0');

-- ============================================================
-- DONE
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;
-- All tables, indexes, defaults, and seed data installed.
-- Visit /install.php to set the default admin password.
