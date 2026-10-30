<?php

namespace Grocy\Helpers;

/**
 * Triggers del historial de cambios (dairy_change_log). Un trigger de SQLite fija la lista de columnas al crearse:
 * toda migración que agregue columnas a una tabla auditada debe llamar a InstallTriggers() para incluirlas.
 */
class DairyChangeLog
{
	const TABLES = ['products', 'product_barcodes', 'shopping_locations', 'customers', 'locations', 'waste_reasons'];

	public static function InstallTriggers(\PDO $db, ?array $tables = null): void
	{
		foreach ($tables ?? self::TABLES as $table)
		{
			$names = array_column($db->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
			if (count($names) === 0)
			{
				continue;
			}
			$json = fn(string $alias) => 'json_object(' . implode(', ', array_map(fn($c) => "'$c', $alias.\"$c\"", $names)) . ')';
			$db->exec("DROP TRIGGER IF EXISTS dairy_log_{$table}_ins; DROP TRIGGER IF EXISTS dairy_log_{$table}_upd; DROP TRIGGER IF EXISTS dairy_log_{$table}_del;");
			$db->exec("CREATE TRIGGER dairy_log_{$table}_ins AFTER INSERT ON $table BEGIN
				INSERT INTO dairy_change_log (table_name, row_id, action, new_json, user_id) VALUES ('$table', NEW.id, 'insert', " . $json('NEW') . ", dairy_user_id()); END");
			$db->exec("CREATE TRIGGER dairy_log_{$table}_upd AFTER UPDATE ON $table WHEN " . $json('OLD') . ' IS NOT ' . $json('NEW') . " BEGIN
				INSERT INTO dairy_change_log (table_name, row_id, action, old_json, new_json, user_id) VALUES ('$table', NEW.id, 'update', " . $json('OLD') . ', ' . $json('NEW') . ", dairy_user_id()); END");
			$db->exec("CREATE TRIGGER dairy_log_{$table}_del AFTER DELETE ON $table BEGIN
				INSERT INTO dairy_change_log (table_name, row_id, action, old_json, user_id) VALUES ('$table', OLD.id, 'delete', " . $json('OLD') . ", dairy_user_id()); END");
		}
	}
}
