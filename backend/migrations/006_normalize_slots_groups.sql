-- Remove camera_id de slots (câmera é metadado de vídeo, não de horário)
-- Adiciona slot_id FK em groups (elimina duplicação de slot_weekday/hour/camera_id)

-- 1. Atualiza unique key de slots (remove camera_id)
ALTER TABLE slots DROP INDEX uq_slot;
ALTER TABLE slots DROP COLUMN camera_id;
ALTER TABLE slots ADD UNIQUE KEY uq_slot (weekday, start_hour, start_minute);

-- 2. Adiciona slot_id em groups (nullable para migrar dados existentes)
ALTER TABLE `groups` ADD COLUMN slot_id INT UNSIGNED NULL AFTER id;

-- 3. Preenche slot_id para grupos existentes
UPDATE `groups` g
JOIN slots s ON s.weekday      = g.slot_weekday
           AND s.start_hour    = g.slot_hour
           AND s.start_minute  = COALESCE(g.slot_minute, 0)
SET g.slot_id = s.id;

-- 4. Aplica NOT NULL e FK
ALTER TABLE `groups` MODIFY COLUMN slot_id INT UNSIGNED NOT NULL;
ALTER TABLE `groups` ADD CONSTRAINT fk_group_slot FOREIGN KEY (slot_id) REFERENCES slots(id);

-- 5. Remove colunas redundantes de groups
ALTER TABLE `groups`
  DROP COLUMN slot_weekday,
  DROP COLUMN slot_hour,
  DROP COLUMN slot_minute,
  DROP COLUMN camera_id;
