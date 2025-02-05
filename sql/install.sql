-- collegamento tra faq ed evento
CREATE TABLE IF NOT EXISTS `#__eb_event_faqsimn` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `event_id` int DEFAULT 0,
    `faq_id` int DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_event_id` (`event_id`),
    KEY `idx_faq_id` (`faq_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- elenco delle faqs
CREATE TABLE IF NOT EXISTS `#__eb_faqsimn` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int UNSIGNED DEFAULT 0,
  `domanda` varchar(255) NOT NULL DEFAULT '',
  `risposta` TEXT NOT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
