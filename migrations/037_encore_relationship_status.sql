ALTER TABLE encore_contacts
  ADD COLUMN relationship_status ENUM('active','inactive','no_response') NOT NULL DEFAULT 'no_response' AFTER stage;
