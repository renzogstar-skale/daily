<?php
$db = new PDO("sqlite:data/grocy_es.db");
$users = $db->query("SELECT u.id, u.username, u.first_name, u.last_name FROM users u")->fetchAll(PDO::FETCH_ASSOC);
echo "=== USERS ===\n";
print_r($users);

echo "=== ROLES ===\n";
$roles = $db->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
print_r($roles);

echo "=== USER ROLES ===\n";
$ur = $db->query("SELECT ur.*, r.name as role_name, u.username FROM user_roles ur JOIN roles r ON r.id = ur.role_id JOIN users u ON u.id = ur.user_id")->fetchAll(PDO::FETCH_ASSOC);
print_r($ur);

echo "=== USER BRANCHES ===\n";
$ub = $db->query("SELECT ub.*, b.name as branch_name, u.username FROM user_branches ub JOIN branches b ON b.id = ub.branch_id JOIN users u ON u.id = ub.user_id")->fetchAll(PDO::FETCH_ASSOC);
print_r($ub);
