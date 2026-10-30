<?php
$db = new PDO('sqlite:data/grocy_es.db');
echo "=== workforce_department_configs ===\n";
print_r($db->query("SELECT * FROM workforce_department_configs")->fetchAll(PDO::FETCH_ASSOC));
echo "=== shift_templates ===\n";
print_r($db->query("SELECT * FROM shift_templates")->fetchAll(PDO::FETCH_ASSOC));
