<?php
exec('git ls-files | grep -i "colombia"', $output);
print_r($output);

