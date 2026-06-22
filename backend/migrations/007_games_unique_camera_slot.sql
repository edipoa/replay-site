-- Garante que cada câmera só tem um game por data+horário
ALTER TABLE games
  ADD UNIQUE KEY uq_game_camera_slot (camera_id, slot_date, slot_hour, slot_minute);
