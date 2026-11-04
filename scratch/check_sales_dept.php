<?php
$db = new PDO('sqlite:data/grocy_es.db');
$salesSum = $db->query("SELECT p.department, SUM(si.amount) as units, SUM(si.total_cost) as cogs
                        FROM sale_items si
                        JOIN sales s ON s.id = si.sale_id
                        JOIN products p ON p.id = si.product_id
                        WHERE s.status = 'completed'
                        GROUP BY p.department")->fetchAll(PDO::FETCH_ASSOC);
print_r($salesSum);
