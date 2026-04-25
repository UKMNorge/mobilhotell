-- Admin: aktive innleveringer med sok
SELECT
  ps.id AS session_id,
  s.id AS slot_id,
  s.slot_number,
  s.slot_type,
  p.qr_code,
  CONCAT(p.first_name, ' ', p.last_name) AS name,
  ps.checkin_time
FROM phone_sessions ps
INNER JOIN participants p ON p.id = ps.participant_id
INNER JOIN slots s ON s.id = ps.slot_id
WHERE ps.status = 'checked_in'
  AND (
    :q = ''
    OR p.qr_code LIKE CONCAT('%', :q, '%')
    OR CONCAT(p.first_name, ' ', p.last_name) LIKE CONCAT('%', :q, '%')
    OR CONCAT(p.last_name, ' ', p.first_name) LIKE CONCAT('%', :q, '%')
  )
ORDER BY ps.checkin_time ASC;

-- Admin: slot-grid med status
SELECT
  s.id,
  s.slot_number,
  s.slot_type,
  s.is_active,
  ps.id AS session_id,
  CONCAT(p.first_name, ' ', p.last_name) AS name,
  p.qr_code
FROM slots s
LEFT JOIN phone_sessions ps
  ON ps.slot_id = s.id
 AND ps.status = 'checked_in'
LEFT JOIN participants p
  ON p.id = ps.participant_id
ORDER BY s.slot_number ASC;

-- Stabilitet: anbefalte indekser
ALTER TABLE participants
  ADD UNIQUE KEY uq_participants_qr_code (qr_code);

ALTER TABLE slots
  ADD UNIQUE KEY uq_slots_slot_number (slot_number),
  ADD KEY idx_slots_type_active (slot_type, is_active, slot_number);

ALTER TABLE phone_sessions
  ADD KEY idx_sessions_participant_status (participant_id, status),
  ADD KEY idx_sessions_slot_status (slot_id, status),
  ADD KEY idx_sessions_status_checkin (status, checkin_time);

-- Kvittering/utleverings-token (for neste steg)
ALTER TABLE phone_sessions
  ADD COLUMN session_token CHAR(64) NULL,
  ADD UNIQUE KEY uq_sessions_token (session_token);

-- Ekstra beskyttelse i DB: maks 1 aktiv session per deltaker og per slot
DELIMITER //
CREATE TRIGGER trg_sessions_before_insert
BEFORE INSERT ON phone_sessions
FOR EACH ROW
BEGIN
  IF NEW.status = 'checked_in' THEN
    IF EXISTS (
      SELECT 1 FROM phone_sessions
      WHERE participant_id = NEW.participant_id
        AND status = 'checked_in'
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'participant_already_checked_in';
    END IF;

    IF EXISTS (
      SELECT 1 FROM phone_sessions
      WHERE slot_id = NEW.slot_id
        AND status = 'checked_in'
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'slot_already_taken';
    END IF;
  END IF;
END//

CREATE TRIGGER trg_sessions_before_update
BEFORE UPDATE ON phone_sessions
FOR EACH ROW
BEGIN
  IF NEW.status = 'checked_in' THEN
    IF EXISTS (
      SELECT 1 FROM phone_sessions
      WHERE participant_id = NEW.participant_id
        AND status = 'checked_in'
        AND id <> NEW.id
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'participant_already_checked_in';
    END IF;

    IF EXISTS (
      SELECT 1 FROM phone_sessions
      WHERE slot_id = NEW.slot_id
        AND status = 'checked_in'
        AND id <> NEW.id
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'slot_already_taken';
    END IF;
  END IF;
END//
DELIMITER ;

-- Dataryddingssporring: finn eventuelle dubletter som ma rettes manuelt
SELECT participant_id, COUNT(*) AS active_count
FROM phone_sessions
WHERE status = 'checked_in'
GROUP BY participant_id
HAVING COUNT(*) > 1;

SELECT slot_id, COUNT(*) AS active_count
FROM phone_sessions
WHERE status = 'checked_in'
GROUP BY slot_id
HAVING COUNT(*) > 1;
