CREATE TABLE IF NOT EXISTS google_calendar_connections (
    user_id int(10) unsigned NOT NULL,
    google_sub varchar(255) NOT NULL,
    email varchar(255) NOT NULL,
    token_ciphertext mediumtext NOT NULL,
    updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (user_id),
    CONSTRAINT fk_google_calendar_connection_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS google_calendar_sources (
    calendar_id int(10) unsigned NOT NULL,
    google_sub varchar(255) NOT NULL,
    google_calendar_id varchar(255) NOT NULL,
    PRIMARY KEY (calendar_id),
    CONSTRAINT fk_google_calendar_source FOREIGN KEY (calendar_id) REFERENCES calendars(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
