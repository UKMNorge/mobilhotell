-- SQLite admin/drift helper queries

-- Aktive innleveringer
SELECT
  ps.id AS session_id,
  s.slot_number,
  s.slot_type,
  p.qr_code,
  p.first_name || ' ' || p.last_name AS name,
  ps.checkin_time
FROM phone_sessions ps
JOIN participants p ON p.id = ps.participant_id
JOIN slots s ON s.id = ps.slot_id
WHERE ps.status = 'checked_in'
ORDER BY ps.checkin_time ASC;

-- Slot-oversikt med status
SELECT
  s.id,
  s.slot_number,
  s.slot_type,
  s.is_active,
  ps.id AS session_id,
  p.qr_code,
  p.first_name || ' ' || p.last_name AS name,
  CASE
    WHEN s.is_active <> 1 THEN 'disabled'
    WHEN ps.id IS NOT NULL THEN 'busy'
    ELSE 'free'
  END AS status
FROM slots s
LEFT JOIN phone_sessions ps ON ps.slot_id = s.id AND ps.status = 'checked_in'
LEFT JOIN participants p ON p.id = ps.participant_id
ORDER BY s.slot_number ASC;

-- Deltakere med samlet skjermfri tid
SELECT
  p.id,
  p.qr_code,
  p.first_name || ' ' || p.last_name AS name,
  COALESCE(SUM(strftime('%s', ps.checkout_time) - strftime('%s', ps.checkin_time)), 0) AS screenfree_seconds
FROM participants p
LEFT JOIN phone_sessions ps ON ps.participant_id = p.id AND ps.checkout_time IS NOT NULL
GROUP BY p.id
ORDER BY name ASC;

-- Siste hendelser
SELECT id, event_type, message, metadata_json, created_at
FROM event_logs
ORDER BY id DESC
LIMIT 100;
