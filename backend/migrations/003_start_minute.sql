-- Adiciona suporte a horários quebrados (ex: 23h30)

ALTER TABLE slots
  ADD COLUMN start_minute TINYINT NOT NULL DEFAULT 0 AFTER start_hour,
  DROP INDEX uq_slot,
  ADD UNIQUE KEY uq_slot (camera_id, weekday, start_hour, start_minute);

ALTER TABLE games
  ADD COLUMN slot_minute TINYINT NOT NULL DEFAULT 0 AFTER slot_hour;

ALTER TABLE `groups`
  ADD COLUMN slot_minute TINYINT NOT NULL DEFAULT 0 AFTER slot_hour;
