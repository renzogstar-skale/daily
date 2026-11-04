<?php
$db = new PDO('sqlite:data/grocy_es.db');
$rows = $db->query("SELECT s.product_id, p.name, s.amount, s.price, l.name as loc, l.branch_id 
                    FROM stock s 
                    JOIN products p ON p.id = s.product_id 
                    JOIN locations l ON l.id = s.location_id 
                    WHERE p.department = 'Tecnología' 
                    LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
