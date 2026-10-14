ALTER TABLE encore_interactions
  ADD COLUMN interaction_kind enum('touch','ask') NOT NULL DEFAULT 'touch' AFTER direction;
