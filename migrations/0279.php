<?php

// DAIRY Etapa 21: etiqueta de ubicación automática y en mayúsculas (el formulario envía '' cuando se deja vacía).
// Separada de 0278 para que la reciban también las bases que ya aplicaron 0278. Solo crea triggers y completa etiquetas vacías.

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
$db->exec("
DROP TRIGGER IF EXISTS dairy_locations_label_ins;
CREATE TRIGGER dairy_locations_label_ins AFTER INSERT ON locations BEGIN
	UPDATE locations SET label_code = CASE WHEN IFNULL(NEW.label_code, '') = '' THEN 'LOC-' || printf('%05d', NEW.id) ELSE upper(NEW.label_code) END WHERE id = NEW.id;
END;
DROP TRIGGER IF EXISTS dairy_locations_label_upd;
CREATE TRIGGER dairy_locations_label_upd AFTER UPDATE OF label_code ON locations WHEN IFNULL(NEW.label_code, '') = '' OR NEW.label_code <> upper(NEW.label_code) BEGIN
	UPDATE locations SET label_code = CASE WHEN IFNULL(NEW.label_code, '') = '' THEN 'LOC-' || printf('%05d', NEW.id) ELSE upper(NEW.label_code) END WHERE id = NEW.id;
END;
UPDATE locations SET label_code = 'LOC-' || printf('%05d', id) WHERE IFNULL(label_code, '') = '';
UPDATE locations SET label_code = upper(label_code) WHERE label_code <> upper(label_code);
");
