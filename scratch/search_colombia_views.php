<?php
exec('git grep -i "colombia" views/', $output);
print_r($output);

