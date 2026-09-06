CREATE TABLE wallet_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    seller_id INT UNSIGNED NOT NULL,
    campaign_id INT UNSIGNED NOT NULL,
    sale_id INT UNSIGNED NOT NULL,
    type ENUM('credit', 'debit') NOT NULL,
    points INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wallet_entries_seller_created_at (seller_id, created_at),
    KEY idx_wallet_entries_campaign_id (campaign_id),
    KEY idx_wallet_entries_sale_id (sale_id),
    CONSTRAINT fk_wallet_entries_seller_id FOREIGN KEY (seller_id) REFERENCES users (id),
    CONSTRAINT fk_wallet_entries_campaign_id FOREIGN KEY (campaign_id) REFERENCES campaigns (id),
    CONSTRAINT fk_wallet_entries_sale_id FOREIGN KEY (sale_id) REFERENCES sales (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
